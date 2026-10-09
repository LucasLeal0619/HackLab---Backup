<?php

namespace Tests\Feature\Jurors;

use App\Domain\Audit\AuditAction;
use App\Domain\Companies\Enums\CompanyStatus;
use App\Domain\Users\Enums\RoleCode;
use App\Models\Company;
use App\Models\CompanyRepresentative;
use App\Models\Event;
use App\Models\Juror;
use App\Models\JurorTeamAssignment;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\QueryException;
use Tests\DatabaseTestCase;

class JurorTest extends DatabaseTestCase
{
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Event::factory()->create();
    }

    private function create(array $payload)
    {
        return $this->actingAs($this->admin())->postJson("/api/v1/events/{$this->event->id}/jurors", $payload);
    }

    public function test_juror_without_account_is_allowed_and_creating_it_does_not_create_a_user(): void
    {
        $users = User::query()->count();

        $this->create(['person' => ['full_name' => 'Ana Jurada', 'email' => 'ana@jurada.test']])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.person.full_name', 'Ana Jurada')
            ->assertJsonPath('data.company', null)
            ->assertJsonPath('data.account', ['has_account' => false, 'account_status' => null, 'account_role' => null, 'access_ready' => false]);

        $this->assertSame($users + 1, User::query()->count()); // só o admin do teste
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::JUROR_CREATED]);
    }

    public function test_access_ready_is_derived_from_the_account_of_the_same_person(): void
    {
        $person = Person::factory()->create();
        User::factory()->role(RoleCode::Juror)->create(['person_id' => $person->id]);

        $id = $this->create(['person_id' => $person->id])->assertCreated()
            ->assertJsonPath('data.account.has_account', true)
            ->assertJsonPath('data.account.account_role', 'JUROR')
            ->assertJsonPath('data.account.access_ready', true)
            ->json('data.id');

        $this->actingAs($this->admin())->patchJson("/api/v1/jurors/{$id}/status", ['status' => 'INACTIVE'])->assertOk()
            ->assertJsonPath('data.account.access_ready', false);
    }

    public function test_user_with_juror_role_does_not_create_a_juror(): void
    {
        User::factory()->role(RoleCode::Juror)->create();

        $this->assertSame(0, Juror::query()->count());
    }

    public function test_representative_becomes_juror_reusing_the_same_person(): void
    {
        $company = Company::factory()->for($this->event)->create();
        $rep = CompanyRepresentative::factory()->for($company)->create();
        $admin = $this->admin();
        $people = Person::query()->count();

        $this->actingAs($admin)->postJson("/api/v1/events/{$this->event->id}/jurors", ['person_id' => $rep->person_id, 'company_id' => $company->id])->assertCreated()
            ->assertJsonPath('data.person.id', $rep->person_id)
            ->assertJsonPath('data.company.id', $company->id);

        $this->assertSame($people, Person::query()->count());
        $this->assertSame(0, JurorTeamAssignment::query()->count()); // empresa não gera atribuição
    }

    public function test_person_is_juror_only_once_per_event(): void
    {
        $person = Person::factory()->create();
        $this->create(['person_id' => $person->id])->assertCreated();

        $this->create(['person_id' => $person->id])->assertUnprocessable()
            ->assertJsonPath('errors.person_id.0', 'Esta pessoa já é jurado(a) deste evento.');

        $this->actingAs($this->admin())->postJson('/api/v1/events/'.Event::factory()->create()->id.'/jurors', ['person_id' => $person->id])->assertCreated();

        $this->expectException(QueryException::class);
        Juror::factory()->for($this->event)->create(['person_id' => $person->id]);
    }

    public function test_company_is_optional_and_must_be_active_and_from_the_same_event(): void
    {
        $foreign = Company::factory()->create();
        $inactive = Company::factory()->for($this->event)->status(CompanyStatus::Inactive)->create();

        $this->create(['person' => ['full_name' => 'X'], 'company_id' => $foreign->id])->assertUnprocessable()
            ->assertJsonPath('errors.company_id.0', 'Empresa inexistente, inativa ou de outro evento.');
        $this->create(['person' => ['full_name' => 'Y'], 'company_id' => $inactive->id])->assertUnprocessable();

        $juror = Juror::factory()->for($this->event)->create();
        $this->expectException(QueryException::class);
        $juror->forceFill(['company_id' => $foreign->id])->save();
    }

    public function test_juror_keeps_company_after_company_inactivation(): void
    {
        $company = Company::factory()->for($this->event)->create();
        $juror = Juror::factory()->for($this->event)->create(['company_id' => $company->id]);
        $company->update(['status' => CompanyStatus::Inactive]);

        $this->actingAs($this->admin())->patchJson("/api/v1/jurors/{$juror->id}", ['company_id' => $company->id, 'notes' => 'ok'])->assertOk()
            ->assertJsonPath('data.company.status', 'INACTIVE');
    }

    public function test_update_status_and_audit(): void
    {
        $juror = Juror::factory()->for($this->event)->create();

        $this->actingAs($this->admin())->patchJson("/api/v1/jurors/{$juror->id}", ['notes' => 'Especialista em IA'])->assertOk();
        $this->actingAs($this->admin())->patchJson("/api/v1/jurors/{$juror->id}/status", ['status' => 'INACTIVE'])->assertOk()->assertJsonPath('data.status', 'INACTIVE');

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::JUROR_UPDATED, 'entity_id' => (string) $juror->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::JUROR_STATUS_CHANGED, 'entity_id' => (string) $juror->id]);
        $this->actingAs($this->admin())->deleteJson("/api/v1/jurors/{$juror->id}")->assertStatus(405);
    }

    public function test_only_admin_manages_or_lists_jurors(): void
    {
        $juror = Juror::factory()->for($this->event)->create();

        foreach ([RoleCode::Manager, RoleCode::Editor, RoleCode::Consultant, RoleCode::Juror, RoleCode::Voter] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->getJson("/api/v1/events/{$this->event->id}/jurors")->assertForbidden();
            $this->actingAs($user)->postJson("/api/v1/events/{$this->event->id}/jurors", ['person' => ['full_name' => 'X']])->assertForbidden();
            $this->actingAs($user)->putJson("/api/v1/jurors/{$juror->id}/assignments", ['team_ids' => []])->assertForbidden();
        }
    }

    public function test_filters_and_pagination(): void
    {
        $company = Company::factory()->for($this->event)->create();
        Juror::factory()->for($this->event)->create(['person_id' => Person::factory()->create(['full_name' => 'Bruna Lima'])->id, 'company_id' => $company->id]);
        Juror::factory()->for($this->event)->inactive()->create(['person_id' => Person::factory()->create(['full_name' => 'Carlos Dias'])->id]);
        $url = "/api/v1/events/{$this->event->id}/jurors";
        $admin = $this->admin();

        $this->actingAs($admin)->getJson("{$url}?search=bruna")->assertJsonCount(1, 'data')->assertJsonPath('data.0.person.full_name', 'Bruna Lima');
        $this->actingAs($admin)->getJson("{$url}?status=INACTIVE")->assertJsonCount(1, 'data')->assertJsonPath('data.0.person.full_name', 'Carlos Dias');
        $this->actingAs($admin)->getJson("{$url}?company_id={$company->id}")->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson("{$url}?per_page=1")->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
    }
}
