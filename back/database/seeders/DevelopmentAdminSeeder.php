<?php

namespace Database\Seeders;

use App\Domain\Users\Enums\RoleCode;
use App\Domain\Users\Enums\UserStatus;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Administrador de DESENVOLVIMENTO/TESTE com credencial previsível.
 *
 * Só roda em APP_ENV local ou testing. Em produção, use: php artisan hacklab:create-admin
 * E-mail e senha podem ser trocados por DEV_ADMIN_EMAIL e DEV_ADMIN_PASSWORD.
 */
class DevelopmentAdminSeeder extends Seeder
{
    public const DEFAULT_EMAIL = 'admin@hacklab.local';

    public const DEFAULT_PASSWORD = 'hacklab123';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->getOutput()->writeln('<comment>DevelopmentAdminSeeder ignorado: só roda em local/testing.</comment>');

            return;
        }

        $email = mb_strtolower((string) (env('DEV_ADMIN_EMAIL') ?: self::DEFAULT_EMAIL));

        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        $person = Person::query()->create([
            'full_name' => 'Administrador de Desenvolvimento',
            'email' => $email,
        ]);

        User::query()->create([
            'person_id' => $person->id,
            'role_id' => Role::forCode(RoleCode::Administrator)->id,
            'email' => $email,
            'password' => (string) (env('DEV_ADMIN_PASSWORD') ?: self::DEFAULT_PASSWORD),
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
    }
}
