<?php

namespace Tests\Feature\Users;

use App\Domain\Audit\AuditAction;
use App\Domain\Users\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;

/**
 * Vínculo setorial: Gestor/Editor sempre com setor; demais perfis sem setor.
 */
class UserSectorTest extends DatabaseTestCase
{
    private Sector $sector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sector = Sector::factory()->create();
    }

    private function create(array $overrides)
    {
        return $this->actingAs($this->admin())->postJson('/api/v1/users', array_replace([
            'person' => ['full_name' => 'Pessoa Teste'],
            'email' => 'pessoa@hacklab.test',
            'password' => 'senhaForte1',
        ], $overrides));
    }

    public function test_manager_and_editor_are_created_with_a_sector(): void
    {
        $this->create(['role' => 'MANAGER', 'sector_id' => $this->sector->id])
            ->assertCreated()
            ->assertJsonPath('data.sector.id', $this->sector->id);

        $this->create(['role' => 'EDITOR', 'sector_id' => $this->sector->id, 'email' => 'editor@hacklab.test'])
            ->assertCreated()
            ->assertJsonPath('data.sector.name', $this->sector->name);
    }

    public function test_manager_or_editor_without_sector_is_rejected(): void
    {
        foreach (['MANAGER', 'EDITOR'] as $role) {
            $this->create(['role' => $role])
                ->assertUnprocessable()
                ->assertJsonPath('errors.sector_id.0', 'Gestor e Editor precisam de um setor.');
        }

        $this->assertSame(0, User::query()->where('email', 'pessoa@hacklab.test')->count());
    }

    public function test_other_profiles_cannot_have_a_sector(): void
    {
        foreach (['ADMINISTRATOR', 'CONSULTANT', 'JUROR', 'VOTER'] as $role) {
            $this->create(['role' => $role, 'sector_id' => $this->sector->id])
                ->assertUnprocessable()
                ->assertJsonPath('errors.sector_id.0', 'Só Gestor e Editor têm vínculo setorial.');
        }
    }

    public function test_inactive_or_missing_sector_is_rejected(): void
    {
        $inactive = Sector::factory()->inactive()->create();

        $this->create(['role' => 'MANAGER', 'sector_id' => $inactive->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.sector_id.0', 'Setor inexistente ou inativo.');

        $this->create(['role' => 'MANAGER', 'sector_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonPath('errors.sector_id.0', 'Setor inexistente ou inativo.');
    }

    public function test_leaving_manager_profile_clears_the_sector(): void
    {
        $manager = $this->sectorUser(RoleCode::Manager, $this->sector);

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/users/{$manager->id}/role", ['role' => 'CONSULTANT'])
            ->assertOk()
            ->assertJsonPath('data.role.code', 'CONSULTANT')
            ->assertJsonPath('data.sector', null);

        $this->assertNull($manager->fresh()->sector_id);

        $log = AuditLog::query()->where('action', AuditAction::USER_ROLE_CHANGED)->sole();
        $this->assertSame($this->sector->id, $log->before_data['sector_id']);
        $this->assertNull($log->after_data['sector_id']);
    }

    public function test_becoming_manager_requires_a_sector(): void
    {
        $consultant = $this->userWithRole(RoleCode::Consultant);

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/users/{$consultant->id}/role", ['role' => 'MANAGER'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.sector_id.0', 'O perfil Gestor exige um setor.');

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/users/{$consultant->id}/role", ['role' => 'MANAGER', 'sector_id' => $this->sector->id])
            ->assertOk()
            ->assertJsonPath('data.sector.id', $this->sector->id);
    }

    public function test_manager_to_editor_keeps_the_sector(): void
    {
        $manager = $this->sectorUser(RoleCode::Manager, $this->sector);

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/users/{$manager->id}/role", ['role' => 'EDITOR'])
            ->assertOk()
            ->assertJsonPath('data.role.code', 'EDITOR')
            ->assertJsonPath('data.sector.id', $this->sector->id);
    }

    public function test_moving_a_manager_to_another_sector_is_audited(): void
    {
        $manager = $this->sectorUser(RoleCode::Manager, $this->sector);
        $target = Sector::factory()->create();

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/users/{$manager->id}/sector", ['sector_id' => $target->id])
            ->assertOk()
            ->assertJsonPath('data.sector.id', $target->id);

        $log = AuditLog::query()->where('action', AuditAction::USER_SECTOR_CHANGED)->sole();
        $this->assertSame($this->sector->id, $log->before_data['sector_id']);
        $this->assertSame($target->id, $log->after_data['sector_id']);
    }

    public function test_profiles_without_sector_cannot_be_moved_to_a_sector(): void
    {
        $consultant = $this->userWithRole(RoleCode::Consultant);

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/users/{$consultant->id}/sector", ['sector_id' => $this->sector->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sector_id']);
    }

    public function test_only_admin_changes_user_sector(): void
    {
        $manager = $this->sectorUser(RoleCode::Manager, $this->sector);
        $other = $this->sectorUser(RoleCode::Editor, $this->sector);

        $this->actingAs($manager)
            ->patchJson("/api/v1/users/{$other->id}/sector", ['sector_id' => Sector::factory()->create()->id])
            ->assertForbidden();
    }

    public function test_database_trigger_keeps_sector_and_role_consistent(): void
    {
        $manager = $this->sectorUser(RoleCode::Manager, $this->sector);

        try {
            // Savepoint: o erro do PostgreSQL não aborta a transação do teste.
            DB::transaction(fn () => DB::table('users')->where('id', $manager->id)->update(['sector_id' => null]));
            $this->fail('Gestor sem setor deveria ser recusado pelo banco.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('exige setor', $e->getMessage());
        }

        $admin = $this->admin();
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $admin->id)->update(['sector_id' => $this->sector->id]);
    }

    public function test_me_shows_the_sector_of_a_manager(): void
    {
        $manager = $this->sectorUser(RoleCode::Manager, $this->sector);

        $this->actingAs($manager)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.sector.id', $this->sector->id)
            ->assertJsonPath('data.sector.event_id', $this->sector->event_id);
    }

    public function test_role_id_lookup_is_by_code_not_by_name(): void
    {
        $this->assertSame('MANAGER', Role::forCode(RoleCode::Manager)->code->value);
    }
}
