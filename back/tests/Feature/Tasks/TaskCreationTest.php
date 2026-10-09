<?php

namespace Tests\Feature\Tasks;

use App\Domain\Audit\AuditAction;
use App\Models\Event;
use App\Models\Sector;
use App\Models\Task;
use Tests\Concerns\DemandScenario;
use Tests\DatabaseTestCase;

class TaskCreationTest extends DatabaseTestCase
{
    use DemandScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildDemandScenario();
    }

    private function create($user, array $payload)
    {
        return $this->actingAs($user)->postJson("/api/v1/events/{$this->event->id}/tasks", array_replace([
            'title' => 'Instalar tomadas',
            'description' => 'Bancadas sem energia.',
        ], $payload));
    }

    public function test_admin_chooses_origin_and_responsible_and_gets_a_generated_reference(): void
    {
        $response = $this->create($this->adminUser, [
            'origin_sector_id' => $this->sectorA->id,
            'responsible_sector_id' => $this->sectorB->id,
            'involved_sector_ids' => [$this->sectorC->id],
            'priority' => 'URGENT',
            'due_at' => '2026-11-10T10:00:00-03:00',
        ])->assertCreated()
            ->assertJsonPath('data.origin_sector.id', $this->sectorA->id)
            ->assertJsonPath('data.responsible_sector.id', $this->sectorB->id)
            ->assertJsonPath('data.involved_sectors.0.id', $this->sectorC->id)
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.priority', 'URGENT');

        $this->assertMatchesRegularExpression('/^PEN-\d{4,}$/', $response->json('data.reference'));

        $second = $this->create($this->adminUser, ['origin_sector_id' => $this->sectorA->id, 'responsible_sector_id' => $this->sectorA->id])->json('data.reference');
        $this->assertGreaterThan((int) substr($response->json('data.reference'), 4), (int) substr($second, 4));

        $task = Task::query()->findOrFail($response->json('data.id'));
        $this->assertSame('CREATED', $task->interactions()->first()->type->value);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TASK_CREATED, 'entity_id' => (string) $task->id]);
    }

    public function test_reference_and_status_cannot_be_provided(): void
    {
        $base = ['origin_sector_id' => $this->sectorA->id, 'responsible_sector_id' => $this->sectorA->id];

        $this->create($this->adminUser, $base + ['reference' => 'PEN-9999'])->assertUnprocessable()->assertJsonValidationErrors(['reference']);
        $this->create($this->adminUser, $base + ['status' => 'COMPLETED'])->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->create($this->adminUser, $base + ['source_occurrence_id' => 1])->assertUnprocessable()->assertJsonValidationErrors(['source_occurrence_id']);
    }

    public function test_manager_creates_from_own_sector_to_any_active_sector(): void
    {
        $this->create($this->manager('A'), ['responsible_sector_id' => $this->sectorB->id, 'involved_sector_ids' => [$this->sectorC->id]])
            ->assertCreated()
            ->assertJsonPath('data.origin_sector.id', $this->sectorA->id)
            ->assertJsonPath('data.responsible_sector.id', $this->sectorB->id);

        $this->create($this->manager('A'), ['origin_sector_id' => $this->sectorB->id, 'responsible_sector_id' => $this->sectorB->id])->assertForbidden();
    }

    public function test_editor_creates_only_for_own_sector(): void
    {
        $this->create($this->editor('A'), [])->assertCreated()
            ->assertJsonPath('data.origin_sector.id', $this->sectorA->id)
            ->assertJsonPath('data.responsible_sector.id', $this->sectorA->id);

        $this->create($this->editor('A'), ['responsible_sector_id' => $this->sectorB->id])->assertForbidden();
        $this->create($this->editor('A'), ['origin_sector_id' => $this->sectorB->id])->assertForbidden();
    }

    public function test_editor_may_assign_only_themselves_on_creation(): void
    {
        $this->create($this->editor('A'), ['assigned_user_id' => $this->editor('A')->id])->assertCreated()
            ->assertJsonPath('data.assigned_user.id', $this->editor('A')->id);

        $this->create($this->editor('A'), ['assigned_user_id' => $this->manager('A')->id])->assertForbidden();
    }

    public function test_inactive_or_foreign_sector_is_rejected(): void
    {
        $inactive = Sector::factory()->for($this->event)->inactive()->create();
        $foreign = Sector::factory()->for(Event::factory())->create();

        foreach ([$inactive, $foreign] as $sector) {
            $this->create($this->adminUser, ['origin_sector_id' => $this->sectorA->id, 'responsible_sector_id' => $sector->id])
                ->assertUnprocessable()
                ->assertJsonPath('errors.responsible_sector_id.0', 'Setor inexistente, inativo ou de outro evento.');

            $this->create($this->adminUser, ['origin_sector_id' => $this->sectorA->id, 'responsible_sector_id' => $this->sectorA->id, 'involved_sector_ids' => [$sector->id]])
                ->assertUnprocessable();
        }
    }

    public function test_involved_list_cannot_repeat_origin_or_responsible(): void
    {
        $this->create($this->adminUser, [
            'origin_sector_id' => $this->sectorA->id,
            'responsible_sector_id' => $this->sectorB->id,
            'involved_sector_ids' => [$this->sectorA->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['involved_sector_ids']);
    }

    public function test_assignee_must_be_active_user_of_the_responsible_sector(): void
    {
        $base = ['origin_sector_id' => $this->sectorA->id, 'responsible_sector_id' => $this->sectorB->id];

        foreach ([$this->manager('A'), $this->adminUser, $this->consultant] as $wrong) {
            $this->create($this->adminUser, $base + ['assigned_user_id' => $wrong->id])
                ->assertUnprocessable()
                ->assertJsonPath('errors.assigned_user_id.0', 'O responsável individual precisa ser usuário ativo do setor responsável.');
        }

        $this->editor('B')->forceFill(['status' => 'INACTIVE'])->save();
        $this->create($this->adminUser, $base + ['assigned_user_id' => $this->editor('B')->id])->assertUnprocessable();

        $this->create($this->adminUser, $base + ['assigned_user_id' => $this->manager('B')->id])->assertCreated();
    }
}
