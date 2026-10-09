<?php

namespace Tests\Feature\Companies;

use App\Domain\Audit\AuditAction;
use App\Domain\Challenges\Enums\ChallengeStatus;
use App\Domain\Companies\Enums\CompanyStatus;
use App\Domain\Users\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Challenge;
use App\Models\Company;
use App\Models\CompanyRepresentative;
use App\Models\Event;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;

class CompanyTest extends DatabaseTestCase
{
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create();
    }

    private function url(): string
    {
        return "/api/v1/events/{$this->event->id}/companies";
    }

    public function test_admin_creates_company_in_the_event_with_normalized_document(): void
    {
        $id = $this->actingAs($this->admin())
            ->postJson($this->url(), [
                'name' => 'Acme Ltda',
                'legal_name' => 'Acme Comércio Ltda',
                'document' => '12.abc.345/0001-99',
                'type' => 'PARTICIPANT',
                'email' => 'CONTATO@ACME.TEST',
            ])
            ->assertCreated()
            ->assertJsonPath('data.event_id', $this->event->id)
            ->assertJsonPath('data.document', '12ABC345000199')
            ->assertJsonPath('data.email', 'contato@acme.test')
            ->assertJsonPath('data.status', 'DRAFT')
            ->json('data.id');

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::COMPANY_CREATED, 'entity_id' => (string) $id]);
    }

    public function test_company_name_is_unique_per_event_ignoring_case(): void
    {
        Company::factory()->for($this->event)->create(['name' => 'Acme']);

        $this->actingAs($this->admin())
            ->postJson($this->url(), ['name' => ' ACME ', 'type' => 'PARTNER'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Já existe uma empresa com este nome neste evento.');

        $other = Event::factory()->create();
        $this->actingAs($this->admin())
            ->postJson("/api/v1/events/{$other->id}/companies", ['name' => 'Acme', 'type' => 'PARTNER'])
            ->assertCreated();

        $this->expectException(QueryException::class);
        Company::factory()->for($this->event)->create(['name' => 'aCmE']);
    }

    public function test_document_is_unique_per_event_after_normalization(): void
    {
        Company::factory()->for($this->event)->create(['document' => '12345678000199']);

        $this->actingAs($this->admin())
            ->postJson($this->url(), ['name' => 'Outra', 'type' => 'OTHER', 'document' => '12.345.678/0001-99'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.document.0', 'Já existe uma empresa com este documento neste evento.');
    }

    public function test_only_valid_types_and_statuses(): void
    {
        $this->actingAs($this->admin())->postJson($this->url(), ['name' => 'X', 'type' => 'FORNECEDOR'])->assertUnprocessable()->assertJsonValidationErrors(['type']);
        $this->actingAs($this->admin())->postJson($this->url(), ['name' => 'X', 'type' => 'OTHER', 'status' => 'INACTIVE'])->assertUnprocessable()->assertJsonValidationErrors(['status']);

        $company = Company::factory()->for($this->event)->create();
        $this->actingAs($this->admin())->patchJson("/api/v1/companies/{$company->id}/status", ['status' => 'COM_DESAFIO'])->assertUnprocessable();
        $this->actingAs($this->admin())->patchJson("/api/v1/companies/{$company->id}", ['status' => 'INACTIVE'])->assertUnprocessable()->assertJsonValidationErrors(['status']);

        $this->expectException(QueryException::class);
        DB::table('companies')->where('id', $company->id)->update(['status' => 'AGUARDANDO_DESAFIO']);
    }

    public function test_inactivation_keeps_challenges_and_representatives(): void
    {
        $company = Company::factory()->for($this->event)->create();
        $challenge = Challenge::factory()->forCompany($company)->create();
        CompanyRepresentative::factory()->for($company)->primary()->create();

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/companies/{$company->id}/status", ['status' => 'INACTIVE'])
            ->assertOk()
            ->assertJsonPath('data.status', 'INACTIVE');

        $this->assertSame($company->id, $challenge->fresh()->company_id);
        $this->assertTrue($company->representatives()->sole()->active);

        $log = AuditLog::query()->where('action', AuditAction::COMPANY_STATUS_CHANGED)->sole();
        $this->assertSame('CONFIRMED', $log->before_data['status']);
        $this->assertSame('INACTIVE', $log->after_data['status']);
    }

    public function test_show_returns_representatives_and_challenge_summary(): void
    {
        $company = Company::factory()->for($this->event)->create();
        CompanyRepresentative::factory()->for($company)->primary()->create();
        CompanyRepresentative::factory()->for($company)->create(['active' => false]);
        Challenge::factory()->forCompany($company)->status(ChallengeStatus::Approved)->create(['title' => 'Desafio A']);

        $this->actingAs($this->admin())
            ->getJson("/api/v1/companies/{$company->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.representatives')
            ->assertJsonPath('data.representatives.0.is_primary', true)
            ->assertJsonPath('data.active_representatives_count', 1)
            ->assertJsonPath('data.challenges.0.title', 'Desafio A')
            ->assertJsonPath('data.challenges.0.team', null)
            ->assertJsonPath('data.challenges_count', 1);
    }

    public function test_update_is_audited_and_no_delete_route_exists(): void
    {
        $company = Company::factory()->for($this->event)->create(['name' => 'Antes']);

        $this->actingAs($this->admin())->patchJson("/api/v1/companies/{$company->id}", ['name' => 'Depois'])->assertOk();
        $log = AuditLog::query()->where('action', AuditAction::COMPANY_UPDATED)->sole();
        $this->assertSame('Antes', $log->before_data['name']);

        $this->actingAs($this->admin())->deleteJson("/api/v1/companies/{$company->id}")->assertStatus(405);
        $this->assertNotNull($company->fresh());
    }

    public function test_filters_and_pagination(): void
    {
        Company::factory()->for($this->event)->create(['name' => 'Alfa', 'type' => 'PARTICIPANT', 'document' => '11111111000111']);
        Company::factory()->for($this->event)->create(['name' => 'Beta', 'type' => 'SPONSOR']);
        Company::factory()->for($this->event)->status(CompanyStatus::Inactive)->create(['name' => 'Gama', 'type' => 'SPONSOR']);
        Company::factory()->create(['name' => 'De outro evento']);

        $admin = $this->admin();
        $names = fn (string $q) => collect($this->actingAs($admin)->getJson($this->url().$q)->assertOk()->json('data'))->pluck('name')->all();

        $this->assertSame(['Alfa', 'Beta', 'Gama'], $names(''));
        $this->assertSame(['Beta', 'Gama'], $names('?type=SPONSOR'));
        $this->assertSame(['Gama'], $names('?status=INACTIVE'));
        $this->assertSame(['Beta'], $names('?search=bet'));
        $this->assertSame(['Alfa'], $names('?search=11.111.111/0001-11'));

        $this->actingAs($admin)->getJson($this->url().'?per_page=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
    }

    public function test_view_and_manage_permissions_per_profile(): void
    {
        $company = Company::factory()->for($this->event)->create();

        foreach ([RoleCode::Manager, RoleCode::Editor, RoleCode::Consultant] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->getJson($this->url())->assertOk();
            $this->actingAs($user)->getJson("/api/v1/companies/{$company->id}")->assertOk();
            $this->actingAs($user)->postJson($this->url(), ['name' => 'X', 'type' => 'OTHER'])->assertForbidden();
            $this->actingAs($user)->patchJson("/api/v1/companies/{$company->id}/status", ['status' => 'INACTIVE'])->assertForbidden();
        }

        foreach ([RoleCode::Juror, RoleCode::Voter] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->getJson($this->url())->assertForbidden();
            $this->actingAs($user)->getJson("/api/v1/companies/{$company->id}")->assertForbidden();
        }
    }
}
