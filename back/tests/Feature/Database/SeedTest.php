<?php

namespace Tests\Feature\Database;

use App\Models\Event;
use App\Models\EventDay;
use App\Models\Meeting;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentAdminSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_exactly_the_six_profiles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['ADMINISTRATOR', 'MANAGER', 'EDITOR', 'CONSULTANT', 'JUROR', 'VOTER'],
            Role::query()->pluck('code')->map->value->all(),
        );
        $this->assertSame('Administrador', Role::query()->where('code', 'ADMINISTRATOR')->value('name'));
        $this->assertSame(0, Role::query()->where('code', 'VALIDATOR')->count());
    }

    public function test_seed_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(6, Role::query()->count());
        $this->assertSame(3, User::query()->count()); // admin + gestor + editor de exemplo
        $this->assertSame(1, Event::query()->count());
        $this->assertSame(3, EventDay::query()->count());
        $this->assertSame(2, Sector::query()->count());
        $this->assertSame(1, Meeting::query()->count());
    }

    public function test_development_admin_exists_in_testing_linked_to_a_person(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', DevelopmentAdminSeeder::DEFAULT_EMAIL)->sole();
        $this->assertSame('ADMINISTRATOR', $admin->role->code->value);
        $this->assertNotNull($admin->person);
        $this->assertTrue(Hash::check(DevelopmentAdminSeeder::DEFAULT_PASSWORD, $admin->password));
    }

    public function test_development_admin_is_never_created_in_production(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->app['env'] = 'production';

        // Direto no seeder: em produção o db:seed ainda pediria confirmação interativa.
        app(DevelopmentAdminSeeder::class)->run();

        $this->app['env'] = 'testing';
        $this->assertSame(0, User::query()->count());
    }
}
