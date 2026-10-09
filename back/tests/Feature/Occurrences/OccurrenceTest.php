<?php

namespace Tests\Feature\Occurrences;

use App\Domain\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\Occurrence;
use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\DemandScenario;
use Tests\DatabaseTestCase;

class OccurrenceTest extends DatabaseTestCase
{
    use DemandScenario;

    private Occurrence $occurrence;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildDemandScenario();

        // Origem A, responsável B, envolvido C.
        $this->occurrence = Occurrence::factory()->between($this->sectorA, $this->sectorB)->create();
        $this->occurrence->involvedSectors()->attach($this->sectorC->id, ['event_id' => $this->event->id]);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/v1/occurrences/{$this->occurrence->id}{$suffix}";
    }

    public function test_occurrence_is_registered_with_day_team_and_reference(): void
    {
        $day = EventDay::factory()->for($this->event)->create(['day_number' => 1, 'date' => $this->event->start_date->toDateString()]);
        $team = Team::factory()->for($this->event)->create();

        $response = $this->actingAs($this->manager('A'))->postJson("/api/v1/events/{$this->event->id}/occurrences", [
            'title' => 'Queda de energia',
            'description' => 'Sem energia no bloco 2.',
            'category' => 'INFRASTRUCTURE',
            'responsible_sector_id' => $this->sectorB->id,
            'event_day_id' => $day->id,
            'team_id' => $team->id,
            'occurred_at' => '2026-11-10T14:30:00-03:00',
            'location' => 'Bloco 2',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'OPEN')
            ->assertJsonPath('data.category', 'INFRASTRUCTURE')
            ->assertJsonPath('data.origin_sector.id', $this->sectorA->id)
            ->assertJsonPath('data.event_day.day_number', 1)
            ->assertJsonPath('data.team.id', $team->id);

        $this->assertMatchesRegularExpression('/^OCO-\d{4,}$/', $response->json('data.reference'));
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::OCCURRENCE_CREATED]);
    }

    public function test_day_and_team_from_another_event_are_rejected_by_api_and_database(): void
    {
        $other = Event::factory()->create();
        $foreignDay = EventDay::factory()->for($other)->create();
        $foreignTeam = Team::factory()->for($other)->create();
        $base = ['title' => 'x', 'description' => 'x', 'category' => 'OTHER', 'origin_sector_id' => $this->sectorA->id, 'responsible_sector_id' => $this->sectorA->id];

        $this->actingAs($this->adminUser)->postJson("/api/v1/events/{$this->event->id}/occurrences", $base + ['event_day_id' => $foreignDay->id])
            ->assertUnprocessable()->assertJsonPath('errors.event_day_id.0', 'Dia inexistente ou de outro evento.');
        $this->actingAs($this->adminUser)->postJson("/api/v1/events/{$this->event->id}/occurrences", $base + ['team_id' => $foreignTeam->id])
            ->assertUnprocessable()->assertJsonPath('errors.team_id.0', 'Equipe inexistente ou de outro evento.');
        $this->actingAs($this->adminUser)->postJson("/api/v1/events/{$this->event->id}/occurrences", array_replace($base, ['category' => 'CLIMA']))
            ->assertUnprocessable()->assertJsonValidationErrors(['category']);

        try {
            DB::transaction(fn () => DB::table('occurrences')->where('id', $this->occurrence->id)->update(['event_day_id' => $foreignDay->id]));
            $this->fail('Dia de outro evento deveria ser recusado pelo banco.');
        } catch (QueryException) {
        }

        $this->expectException(QueryException::class);
        DB::table('occurrences')->where('id', $this->occurrence->id)->update(['team_id' => $foreignTeam->id]);
    }

    public function test_resolve_requires_a_solution(): void
    {
        $this->actingAs($this->manager('B'))->postJson($this->url('/resolve'), ['resolution' => '   '])
            ->assertUnprocessable()->assertJsonValidationErrors(['resolution']);
        $this->actingAs($this->manager('B'))->patchJson($this->url(), ['status' => 'RESOLVED'])
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->actingAs($this->manager('B'))->patchJson($this->url(), ['resolution' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors(['resolution']);
    }

    public function test_resolve_and_reopen_preserve_previous_solutions(): void
    {
        $manager = $this->manager('B');

        $this->actingAs($manager)->postJson($this->url('/resolve'), ['resolution' => 'Disjuntor religado.'])->assertOk()
            ->assertJsonPath('data.status', 'RESOLVED')
            ->assertJsonPath('data.resolution', 'Disjuntor religado.');
        $this->assertNotNull($this->occurrence->fresh()->resolved_at);

        $this->actingAs($manager)->postJson($this->url('/reopen'), ['message' => 'Voltou a cair'])->assertOk()
            ->assertJsonPath('data.status', 'OPEN')
            ->assertJsonPath('data.resolved_at', null)
            ->assertJsonPath('data.resolution', 'Disjuntor religado.'); // não apaga

        $this->actingAs($manager)->postJson($this->url('/resolve'), ['resolution' => 'Gerador ligado.'])->assertOk()
            ->assertJsonPath('data.resolution', 'Gerador ligado.');

        $second = $this->occurrence->interactions()->where('type', 'RESOLVED')->get()->last();
        $this->assertSame('Disjuntor religado.', $second->metadata['previous_resolution']);

        $log = AuditLog::query()->where('action', AuditAction::OCCURRENCE_RESOLVED)->orderByDesc('id')->first();
        $this->assertSame('Disjuntor religado.', $log->before_data['resolution']);
        $this->assertSame('Gerador ligado.', $log->after_data['resolution']);
    }

    public function test_repeated_resolution_or_reopen_is_rejected_without_changes(): void
    {
        $this->actingAs($this->manager('B'))->postJson($this->url('/reopen'))->assertUnprocessable();
        $this->actingAs($this->manager('B'))->postJson($this->url('/resolve'), ['resolution' => 'ok'])->assertOk();
        $this->actingAs($this->manager('B'))->postJson($this->url('/resolve'), ['resolution' => 'de novo'])->assertUnprocessable();

        $this->assertSame('ok', $this->occurrence->fresh()->resolution);
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::OCCURRENCE_RESOLVED)->count());
    }

    public function test_sector_rules_mirror_tasks(): void
    {
        // Editor responsável: status e resolver; não estrutura nem encaminha.
        $this->actingAs($this->editor('B'))->patchJson($this->url(), ['status' => 'IN_PROGRESS'])->assertOk();
        $this->actingAs($this->editor('B'))->patchJson($this->url(), ['category' => 'TEAM'])->assertForbidden();
        $this->actingAs($this->editor('B'))->postJson($this->url('/forward'), ['sector_id' => $this->sectorD->id, 'reason' => 'x'])->assertForbidden();

        // Origem e envolvido acompanham e comentam.
        foreach (['A', 'C'] as $key) {
            $this->actingAs($this->manager($key))->postJson($this->url('/comments'), ['message' => 'ok'])->assertCreated();
            $this->actingAs($this->manager($key))->postJson($this->url('/resolve'), ['resolution' => 'x'])->assertForbidden();
        }

        // Consultor comenta, sem operar. Setor D não vê.
        $this->actingAs($this->consultant)->postJson($this->url('/comments'), ['message' => 'ok'])->assertCreated();
        $this->actingAs($this->consultant)->postJson($this->url('/resolve'), ['resolution' => 'x'])->assertForbidden();
        $this->actingAs($this->manager('D'))->getJson($this->url())->assertForbidden();

        // Gestor responsável encaminha: B vira envolvido.
        $this->actingAs($this->manager('B'))->postJson($this->url('/forward'), ['sector_id' => $this->sectorD->id, 'reason' => 'Equipe de TI'])->assertOk()
            ->assertJsonPath('data.responsible_sector.id', $this->sectorD->id);
        $this->assertEqualsCanonicalizing([$this->sectorB->id, $this->sectorC->id], $this->occurrence->fresh()->involvedSectors->pluck('id')->all());
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::OCCURRENCE_FORWARDED)->count());
    }

    public function test_no_delete_route(): void
    {
        $this->actingAs($this->adminUser)->deleteJson($this->url())->assertStatus(405);
    }
}
