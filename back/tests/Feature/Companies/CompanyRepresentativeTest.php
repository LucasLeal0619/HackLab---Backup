<?php

namespace Tests\Feature\Companies;

use App\Domain\Audit\AuditAction;
use App\Domain\Companies\Enums\CompanyStatus;
use App\Domain\Users\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyRepresentative;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\QueryException;
use Tests\DatabaseTestCase;

class CompanyRepresentativeTest extends DatabaseTestCase
{
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
    }

    private function add(array $payload)
    {
        return $this->actingAs($this->admin())->postJson("/api/v1/companies/{$this->company->id}/representatives", $payload);
    }

    public function test_representative_creates_person_without_user(): void
    {
        $usersBefore = User::query()->count();

        $this->add(['person' => ['full_name' => 'Marta Reis', 'email' => 'marta@acme.test'], 'title' => 'Diretora', 'is_primary' => true])
            ->assertCreated()
            ->assertJsonPath('data.person.full_name', 'Marta Reis')
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('data.active', true);

        $person = Person::query()->where('email', 'marta@acme.test')->sole();
        $this->assertNull($person->user);
        // Só o admin do teste foi criado; o representante não ganhou conta.
        $this->assertSame($usersBefore + 1, User::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::COMPANY_REPRESENTATIVE_ADDED]);
    }

    public function test_existing_person_becomes_representative(): void
    {
        $person = Person::factory()->create();

        $this->add(['person_id' => $person->id])->assertCreated()->assertJsonPath('data.person.id', $person->id);
    }

    public function test_person_is_not_duplicated_within_the_same_company(): void
    {
        $person = Person::factory()->create();
        CompanyRepresentative::factory()->for($this->company)->create(['person_id' => $person->id]);

        $this->add(['person_id' => $person->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.person_id.0', 'Esta pessoa já é representante desta empresa.');

        // A mesma pessoa pode representar outra empresa.
        $other = Company::factory()->create();
        $this->actingAs($this->admin())->postJson("/api/v1/companies/{$other->id}/representatives", ['person_id' => $person->id])->assertCreated();
    }

    public function test_only_one_active_primary_representative(): void
    {
        CompanyRepresentative::factory()->for($this->company)->primary()->create();

        $this->add(['person' => ['full_name' => 'Segundo'], 'is_primary' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_primary']);

        $second = CompanyRepresentative::factory()->for($this->company)->create();
        $this->actingAs($this->admin())
            ->patchJson("/api/v1/company-representatives/{$second->id}", ['is_primary' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_primary']);

        $this->expectException(QueryException::class);
        CompanyRepresentative::factory()->for($this->company)->primary()->create();
    }

    public function test_primary_can_be_switched_explicitly(): void
    {
        $first = CompanyRepresentative::factory()->for($this->company)->primary()->create();
        $second = CompanyRepresentative::factory()->for($this->company)->create();
        $admin = $this->admin();

        $this->actingAs($admin)->patchJson("/api/v1/company-representatives/{$first->id}", ['is_primary' => false])->assertOk();
        $this->actingAs($admin)->patchJson("/api/v1/company-representatives/{$second->id}", ['is_primary' => true])->assertOk()->assertJsonPath('data.is_primary', true);

        $this->assertSame(2, AuditLog::query()->where('action', AuditAction::COMPANY_REPRESENTATIVE_UPDATED)->count());
    }

    public function test_deactivation_keeps_history_and_clears_primary(): void
    {
        $rep = CompanyRepresentative::factory()->for($this->company)->primary()->create();

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/company-representatives/{$rep->id}/status", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.is_primary', false);

        $this->assertNotNull($rep->fresh());
        $log = AuditLog::query()->where('action', AuditAction::COMPANY_REPRESENTATIVE_STATUS_CHANGED)->sole();
        $this->assertTrue($log->before_data['is_primary']);
        $this->assertFalse($log->after_data['is_primary']);

        $this->actingAs($this->admin())->getJson("/api/v1/companies/{$this->company->id}/representatives?active=0")->assertJsonCount(1, 'data');
        $this->actingAs($this->admin())->getJson("/api/v1/companies/{$this->company->id}/representatives?active=1")->assertJsonCount(0, 'data');
    }

    public function test_inactive_company_does_not_receive_active_representatives(): void
    {
        $rep = CompanyRepresentative::factory()->for($this->company)->create(['active' => false]);
        $this->company->update(['status' => CompanyStatus::Inactive]);

        $this->add(['person' => ['full_name' => 'Novo']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company']);

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/company-representatives/{$rep->id}/status", ['active' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['active']);
    }

    public function test_draft_company_may_have_no_representative(): void
    {
        $draft = Company::factory()->status(CompanyStatus::Draft)->create();

        $this->actingAs($this->admin())->getJson("/api/v1/companies/{$draft->id}")->assertOk()->assertJsonCount(0, 'data.representatives');
    }

    public function test_only_company_managers_handle_representatives(): void
    {
        $rep = CompanyRepresentative::factory()->for($this->company)->create();

        foreach ([RoleCode::Manager, RoleCode::Editor, RoleCode::Consultant] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->getJson("/api/v1/companies/{$this->company->id}/representatives")->assertOk();
            $this->actingAs($user)->postJson("/api/v1/companies/{$this->company->id}/representatives", ['person' => ['full_name' => 'X']])->assertForbidden();
            $this->actingAs($user)->patchJson("/api/v1/company-representatives/{$rep->id}/status", ['active' => false])->assertForbidden();
        }

        foreach ([RoleCode::Juror, RoleCode::Voter] as $role) {
            $this->actingAs($this->userWithRole($role))->getJson("/api/v1/companies/{$this->company->id}/representatives")->assertForbidden();
        }
    }
}
