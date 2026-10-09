<?php

namespace Tests\Feature\Challenges;

use App\Domain\Audit\AuditAction;
use App\Domain\Challenges\Enums\ChallengeStatus;
use App\Domain\Companies\Enums\CompanyStatus;
use App\Domain\Users\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Challenge;
use App\Models\Company;
use App\Models\Event;
use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;

class ChallengeTest extends DatabaseTestCase
{
    private Event $event;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create();
        $this->company = Company::factory()->for($this->event)->create(['name' => 'Acme']);
    }

    private function url(): string
    {
        return "/api/v1/events/{$this->event->id}/challenges";
    }

    public function test_challenge_with_company_keeps_structured_fields(): void
    {
        $id = $this->actingAs($this->admin())
            ->postJson($this->url(), [
                'company_id' => $this->company->id,
                'title' => 'Rota de entregas',
                'problem' => 'Entregas atrasam.',
                'objective' => 'Reduzir atrasos.',
                'requirements' => 'API pública.',
                'restrictions' => 'Sem dados pessoais.',
                'expected_outcome' => 'Protótipo.',
                'notes' => 'Prioridade alta.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.company.name', 'Acme')
            ->assertJsonPath('data.objective', 'Reduzir atrasos.')
            ->assertJsonPath('data.restrictions', 'Sem dados pessoais.')
            ->assertJsonPath('data.team', null)
            ->json('data.id');

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::CHALLENGE_CREATED, 'entity_id' => (string) $id]);
    }

    public function test_institutional_challenge_without_company(): void
    {
        $this->actingAs($this->admin())
            ->postJson($this->url(), ['title' => 'Desafio Senac', 'problem' => 'P'])
            ->assertCreated()
            ->assertJsonPath('data.company', null);
    }

    public function test_company_from_another_event_is_rejected_by_api_and_database(): void
    {
        $foreign = Company::factory()->create();

        $this->actingAs($this->admin())
            ->postJson($this->url(), ['title' => 'X', 'problem' => 'P', 'company_id' => $foreign->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.company_id.0', 'Empresa inexistente, inativa ou de outro evento.');

        $challenge = Challenge::factory()->for($this->event)->create();
        $this->expectException(QueryException::class);
        DB::table('challenges')->where('id', $challenge->id)->update(['company_id' => $foreign->id]);
    }

    public function test_inactive_company_does_not_receive_challenges(): void
    {
        $inactive = Company::factory()->for($this->event)->status(CompanyStatus::Inactive)->create();

        $this->actingAs($this->admin())
            ->postJson($this->url(), ['title' => 'X', 'problem' => 'P', 'company_id' => $inactive->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);

        $challenge = Challenge::factory()->forCompany($this->company)->create();
        $this->actingAs($this->admin())
            ->patchJson("/api/v1/challenges/{$challenge->id}", ['company_id' => $inactive->id])
            ->assertUnprocessable();
    }

    public function test_existing_challenge_survives_company_inactivation_and_can_still_be_edited(): void
    {
        $challenge = Challenge::factory()->forCompany($this->company)->create();
        $this->company->update(['status' => CompanyStatus::Inactive]);

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/challenges/{$challenge->id}", ['company_id' => $this->company->id, 'notes' => 'ok'])
            ->assertOk()
            ->assertJsonPath('data.company.status', 'INACTIVE');
    }

    public function test_status_flow_and_semantic_rules(): void
    {
        $challenge = Challenge::factory()->for($this->event)->create();
        $admin = $this->admin();

        foreach (['RECEIVED', 'UNDER_REVIEW', 'APPROVED'] as $status) {
            $this->actingAs($admin)->patchJson("/api/v1/challenges/{$challenge->id}/status", ['status' => $status])->assertOk()->assertJsonPath('data.status', $status);
        }

        foreach (['DISTRIBUTED', 'IN_DEVELOPMENT'] as $status) {
            $this->actingAs($admin)
                ->patchJson("/api/v1/challenges/{$challenge->id}/status", ['status' => $status])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['status']);
        }

        $this->actingAs($admin)->patchJson("/api/v1/challenges/{$challenge->id}/status", ['status' => 'CANCELADO'])->assertUnprocessable();
        $this->actingAs($admin)->patchJson("/api/v1/challenges/{$challenge->id}", ['status' => 'APPROVED'])->assertUnprocessable()->assertJsonValidationErrors(['status']);

        $this->assertSame(3, AuditLog::query()->where('action', AuditAction::CHALLENGE_STATUS_CHANGED)->count());

        $this->expectException(QueryException::class);
        DB::table('challenges')->where('id', $challenge->id)->update(['status' => 'DELETED']);
    }

    public function test_creation_only_accepts_pre_distribution_status(): void
    {
        $this->actingAs($this->admin())->postJson($this->url(), ['title' => 'A', 'problem' => 'P', 'status' => 'APPROVED'])->assertCreated()->assertJsonPath('data.status', 'APPROVED');
        $this->actingAs($this->admin())->postJson($this->url(), ['title' => 'B', 'problem' => 'P', 'status' => 'DISTRIBUTED'])->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->actingAs($this->admin())->postJson($this->url(), ['title' => 'C', 'problem' => 'P', 'team_id' => 1])->assertUnprocessable()->assertJsonValidationErrors(['team_id']);
    }

    public function test_no_delete_route_for_challenges(): void
    {
        $challenge = Challenge::factory()->for($this->event)->create();

        $this->actingAs($this->admin())->deleteJson("/api/v1/challenges/{$challenge->id}")->assertStatus(405);
    }

    public function test_filters_and_pagination(): void
    {
        $team = Team::factory()->for($this->event)->create();
        $distributed = Challenge::factory()->forCompany($this->company)->status(ChallengeStatus::Distributed)->create(['title' => 'Alfa']);
        $team->forceFill(['challenge_id' => $distributed->id])->save();
        Challenge::factory()->forCompany($this->company)->status(ChallengeStatus::Approved)->create(['title' => 'Beta']);
        Challenge::factory()->for($this->event)->status(ChallengeStatus::Approved)->create(['title' => 'Gama institucional']);
        Challenge::factory()->create(['title' => 'De outro evento']);

        $admin = $this->admin();
        $titles = fn (string $q) => collect($this->actingAs($admin)->getJson($this->url().$q)->assertOk()->json('data'))->pluck('title')->all();

        $this->assertSame(['Alfa', 'Beta', 'Gama institucional'], $titles(''));
        $this->assertSame(['Beta', 'Gama institucional'], $titles('?status=APPROVED'));
        $this->assertSame(['Alfa', 'Beta'], $titles("?company_id={$this->company->id}"));
        $this->assertSame(['Alfa', 'Beta'], $titles('?has_company=1'));
        $this->assertSame(['Gama institucional'], $titles('?has_company=0'));
        $this->assertSame(['Alfa'], $titles('?has_team=1'));
        $this->assertSame(['Beta', 'Gama institucional'], $titles('?has_team=0'));
        $this->assertSame(['Gama institucional'], $titles('?search=gama'));

        $this->actingAs($admin)->getJson($this->url().'?per_page=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
        $this->actingAs($admin)->getJson("/api/v1/challenges/{$distributed->id}")->assertJsonPath('data.team.id', $team->id)->assertJsonPath('data.company.id', $this->company->id);
    }

    public function test_view_and_manage_permissions_per_profile(): void
    {
        $challenge = Challenge::factory()->for($this->event)->status(ChallengeStatus::Approved)->create();
        $team = Team::factory()->for($this->event)->create();

        foreach ([RoleCode::Manager, RoleCode::Editor, RoleCode::Consultant] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->getJson($this->url())->assertOk();
            $this->actingAs($user)->getJson("/api/v1/challenges/{$challenge->id}")->assertOk();
            $this->actingAs($user)->postJson($this->url(), ['title' => 'X', 'problem' => 'P'])->assertForbidden();
            $this->actingAs($user)->patchJson("/api/v1/challenges/{$challenge->id}/status", ['status' => 'RECEIVED'])->assertForbidden();
            $this->actingAs($user)->patchJson("/api/v1/challenges/{$challenge->id}/team", ['team_id' => $team->id])->assertForbidden();
        }

        foreach ([RoleCode::Juror, RoleCode::Voter] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->getJson($this->url())->assertForbidden();
            $this->actingAs($user)->getJson("/api/v1/challenges/{$challenge->id}")->assertForbidden();
        }

        $this->assertNull($team->fresh()->challenge_id);
    }
}
