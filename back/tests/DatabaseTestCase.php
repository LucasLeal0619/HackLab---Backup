<?php

namespace Tests;

use App\Domain\Users\Enums\RoleCode;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Testes que usam o banco (PostgreSQL hacklab_test) com perfis e permissões já semeados.
 */
abstract class DatabaseTestCase extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = RolePermissionSeeder::class;

    /** Origem da SPA configurada em SANCTUM_STATEFUL_DOMAINS: ativa sessão por cookie. */
    protected const SPA_ORIGIN = 'http://localhost:5174';

    protected function userWithRole(RoleCode $role, array $attributes = []): User
    {
        return User::factory()->role($role)->create($attributes);
    }

    protected function admin(array $attributes = []): User
    {
        return $this->userWithRole(RoleCode::Administrator, $attributes);
    }

    /**
     * Requisições como se viessem do frontend (stateful no Sanctum).
     */
    protected function fromSpa(): static
    {
        return $this->withHeader('Origin', self::SPA_ORIGIN);
    }
}
