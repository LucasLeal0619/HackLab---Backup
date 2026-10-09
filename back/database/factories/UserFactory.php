<?php

namespace Database\Factories;

use App\Domain\Users\Enums\RoleCode;
use App\Domain\Users\Enums\UserStatus;
use App\Models\Person;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 *
 * Exige os perfis no banco (RolePermissionSeeder). Perfil padrão: Votante.
 * Gestor/Editor recebem um setor (novo, ou o informado em inSector()).
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'role_id' => fn () => Role::forCode(RoleCode::Voter)->id,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password1'),
            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    public function role(RoleCode $code): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::forCode($code)->id,
            'sector_id' => $code->requiresSector() ? ($attributes['sector_id'] ?? Sector::factory()) : null,
        ]);
    }

    public function inSector(Sector $sector): static
    {
        return $this->state(fn () => ['sector_id' => $sector->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Inactive]);
    }
}
