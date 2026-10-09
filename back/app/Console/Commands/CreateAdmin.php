<?php

namespace App\Console\Commands;

use App\Domain\Users\Enums\RoleCode;
use App\Domain\Users\UserService;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Cria um Administrador sem credencial fixa (uso em produção/homologação).
 * A senha é digitada no terminal e nunca aparece em log nem no histórico do shell.
 */
class CreateAdmin extends Command
{
    protected $signature = 'hacklab:create-admin';

    protected $description = 'Cria uma conta de Administrador (pessoa + usuário), pedindo a senha no terminal';

    public function handle(UserService $users): int
    {
        if (! Role::query()->where('code', RoleCode::Administrator->value)->exists()) {
            $this->error('Perfis não encontrados. Rode antes: php artisan db:seed --class=RolePermissionSeeder');

            return self::FAILURE;
        }

        $data = [
            'person' => ['full_name' => (string) $this->ask('Nome completo')],
            'email' => mb_strtolower(trim((string) $this->ask('E-mail'))),
            'password' => (string) $this->secret('Senha (mínimo 8, letras e números)'),
            'role' => RoleCode::Administrator->value,
        ];

        if ($data['password'] !== (string) $this->secret('Confirme a senha')) {
            $this->error('As senhas não conferem.');

            return self::FAILURE;
        }

        $validator = Validator::make($data, [
            'person.full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = $users->create($data);
        $this->info("Administrador {$user->email} criado.");

        return self::SUCCESS;
    }
}
