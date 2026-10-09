<?php

namespace Tests\Feature\Users;

use App\Domain\Audit\AuditAction;
use App\Domain\Users\Enums\RoleCode;
use App\Domain\Users\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\DatabaseTestCase;

class UserManagementTest extends DatabaseTestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'person' => ['full_name' => 'Maria Souza', 'phone' => '11999990000'],
            'email' => 'Maria@HackLab.test',
            'password' => 'senhaForte1',
            'role' => 'EDITOR',
        ], $overrides);
    }

    public function test_admin_creates_person_and_user_together(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/v1/users', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.email', 'maria@hacklab.test')
            ->assertJsonPath('data.role.code', 'EDITOR')
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.person.full_name', 'Maria Souza')
            ->assertJsonMissingPath('data.password');

        $user = User::query()->where('email', 'maria@hacklab.test')->sole();
        $this->assertSame('Maria Souza', $user->person->full_name);
        $this->assertSame('maria@hacklab.test', $user->person->email);
        $this->assertTrue(Hash::check('senhaForte1', $user->password));
        $this->assertNotSame('senhaForte1', $user->getRawOriginal('password'));

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::PERSON_CREATED, 'entity_id' => (string) $user->person_id]);
        $created = AuditLog::query()->where('action', AuditAction::USER_CREATED)->sole();
        $this->assertSame((string) $user->id, $created->entity_id);
        $this->assertStringNotContainsString('senhaForte1', json_encode($created->toArray()));
        $this->assertArrayNotHasKey('password', $created->after_data);
    }

    public function test_admin_creates_account_for_an_existing_person(): void
    {
        $person = Person::factory()->create();

        $this->actingAs($this->admin())
            ->postJson('/api/v1/users', $this->validPayload(['person' => null, 'person_id' => $person->id]))
            ->assertCreated()
            ->assertJsonPath('data.person.id', $person->id);

        $this->assertSame(1, $person->fresh()->user()->count());
    }

    public function test_a_person_cannot_have_two_accounts(): void
    {
        $existing = User::factory()->create();

        $this->actingAs($this->admin())
            ->postJson('/api/v1/users', $this->validPayload(['person' => null, 'person_id' => $existing->person_id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.person_id.0', 'Esta pessoa já possui uma conta.');
    }

    public function test_person_can_exist_without_account(): void
    {
        $person = Person::factory()->create();

        $this->assertNull($person->user);
        $this->assertSame(0, User::query()->where('person_id', $person->id)->count());
    }

    public function test_email_must_be_unique_ignoring_case(): void
    {
        User::factory()->create(['email' => 'maria@hacklab.test']);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/users', $this->validPayload(['email' => 'MARIA@hacklab.test']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Já existe uma conta com este e-mail.');
    }

    public function test_database_rejects_uppercase_email(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('users')->where('id', $user->id)->update(['email' => 'MAIUSCULO@hacklab.test']);
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/v1/users', $this->validPayload(['password' => 'curta']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_role_must_be_one_of_the_six_profiles(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/v1/users', $this->validPayload(['role' => 'VALIDATOR']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_non_admin_profiles_cannot_create_users(): void
    {
        foreach ([RoleCode::Manager, RoleCode::Editor, RoleCode::Consultant, RoleCode::Juror, RoleCode::Voter] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->postJson('/api/v1/users', $this->validPayload(['email' => strtolower($role->value).'@hacklab.test']))
                ->assertForbidden();
        }

        $this->assertSame(0, User::query()->where('email', 'like', '%@hacklab.test')->count());
    }

    public function test_only_admin_lists_users(): void
    {
        $this->actingAs($this->userWithRole(RoleCode::Consultant))->getJson('/api/v1/users')->assertForbidden();

        User::factory()->count(3)->create();

        $this->actingAs($this->admin())
            ->getJson('/api/v1/users?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonStructure(['data' => [['id', 'email', 'status', 'role' => ['code', 'name'], 'person']], 'links', 'meta']);
    }

    public function test_user_sees_own_account_but_not_others(): void
    {
        $voter = $this->userWithRole(RoleCode::Voter);
        $other = User::factory()->create();

        $this->actingAs($voter)->getJson("/api/v1/users/{$voter->id}")->assertOk();
        $this->actingAs($voter)->getJson("/api/v1/users/{$other->id}")->assertForbidden();
    }

    public function test_admin_updates_email_and_password_with_audit(): void
    {
        $user = User::factory()->create(['email' => 'antigo@hacklab.test']);

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/users/{$user->id}", ['email' => 'novo@hacklab.test', 'password' => 'outraSenha9'])
            ->assertOk()
            ->assertJsonPath('data.email', 'novo@hacklab.test');

        $this->assertTrue(Hash::check('outraSenha9', $user->fresh()->password));

        $log = AuditLog::query()->where('action', AuditAction::USER_UPDATED)->sole();
        $this->assertSame('antigo@hacklab.test', $log->before_data['email']);
        $this->assertSame('novo@hacklab.test', $log->after_data['email']);
        $this->assertTrue($log->after_data['credential_changed']);
        $this->assertStringNotContainsString('outraSenha9', json_encode($log->toArray()));
    }

    public function test_admin_changes_role_with_audit(): void
    {
        $user = $this->userWithRole(RoleCode::Voter);

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/users/{$user->id}/role", ['role' => 'JUROR'])
            ->assertOk()
            ->assertJsonPath('data.role.code', 'JUROR');

        $log = AuditLog::query()->where('action', AuditAction::USER_ROLE_CHANGED)->sole();
        $this->assertSame('VOTER', $log->before_data['role']);
        $this->assertSame('JUROR', $log->after_data['role']);
    }

    public function test_last_active_admin_cannot_be_demoted_or_inactivated(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patchJson("/api/v1/users/{$admin->id}/role", ['role' => 'VOTER'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.role.0', 'Não é possível remover o último Administrador ativo.');

        $this->actingAs($admin)
            ->patchJson("/api/v1/users/{$admin->id}/status", ['status' => 'INACTIVE'])
            ->assertUnprocessable();

        $this->assertTrue($admin->fresh()->hasRole(RoleCode::Administrator));
        $this->assertSame(UserStatus::Active, $admin->fresh()->status);
    }

    public function test_inactivation_ends_sessions_and_reactivation_works(): void
    {
        $user = $this->userWithRole(RoleCode::Editor);
        DB::table('sessions')->insert([
            'id' => 'sessao-teste', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patchJson("/api/v1/users/{$user->id}/status", ['status' => 'INACTIVE'])
            ->assertOk()
            ->assertJsonPath('data.status', 'INACTIVE');

        $this->assertDatabaseMissing('sessions', ['id' => 'sessao-teste']);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::USER_INACTIVATED, 'entity_id' => (string) $user->id]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/users/{$user->id}/status", ['status' => 'ACTIVE'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::USER_ACTIVATED, 'entity_id' => (string) $user->id]);
    }

    public function test_roles_endpoint_lists_the_six_profiles_for_admin_only(): void
    {
        $this->actingAs($this->userWithRole(RoleCode::Manager))->getJson('/api/v1/roles')->assertForbidden();

        $this->actingAs($this->admin())
            ->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.code', 'ADMINISTRATOR');
    }
}
