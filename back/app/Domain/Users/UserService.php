<?php

namespace App\Domain\Users;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\People\PersonService;
use App\Domain\Users\Enums\RoleCode;
use App\Domain\Users\Enums\UserStatus;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Contas de acesso. Toda conta pertence a uma Person (existente ou criada junto).
 */
class UserService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PersonService $people,
    ) {}

    /**
     * @param  array{
     *     person_id?: int,
     *     person?: array{full_name: string, phone?: ?string, document?: ?string},
     *     email: string,
     *     password: string,
     *     role: string,
     *     status?: string,
     * }  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $person = isset($data['person_id'])
                ? Person::query()->lockForUpdate()->findOrFail($data['person_id'])
                : $this->people->create([
                    'full_name' => $data['person']['full_name'],
                    'email' => $data['email'],
                    'phone' => $data['person']['phone'] ?? null,
                    'document' => $data['person']['document'] ?? null,
                ]);

            $user = User::query()->create([
                'person_id' => $person->id,
                'role_id' => Role::forCode(RoleCode::from($data['role']))->id,
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => $data['status'] ?? UserStatus::Active->value,
            ]);

            $this->audit->record(
                AuditAction::USER_CREATED,
                'users',
                "Conta {$user->email} criada para {$person->full_name}.",
                entity: $user,
                after: $this->snapshot($user),
            );

            return $user->load('person', 'role');
        });
    }

    /**
     * Dados da conta (e-mail e senha). Perfil e status têm operações próprias.
     *
     * @param  array{email?: string, password?: string}  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $before = $this->snapshot($user);
            $user->fill($data);
            $credentialChanged = $user->isDirty('password');

            if (! $user->isDirty()) {
                return $user->load('person', 'role');
            }

            $user->save();

            $this->audit->record(
                AuditAction::USER_UPDATED,
                'users',
                "Conta {$user->email} alterada.",
                entity: $user,
                before: $before,
                after: $this->snapshot($user) + ['credential_changed' => $credentialChanged],
            );

            return $user->load('person', 'role');
        });
    }

    public function changeRole(User $user, RoleCode $code): User
    {
        return DB::transaction(function () use ($user, $code) {
            $user->loadMissing('role');

            if ($user->role->code === $code) {
                return $user->load('person', 'role');
            }

            if ($user->hasRole(RoleCode::Administrator)) {
                $this->ensureAnotherActiveAdministrator($user, 'role');
            }

            $before = $this->snapshot($user);
            $user->role()->associate(Role::forCode($code))->save();

            $this->audit->record(
                AuditAction::USER_ROLE_CHANGED,
                'users',
                "Perfil de {$user->email} alterado de {$before['role']} para {$code->value}.",
                entity: $user,
                before: $before,
                after: $this->snapshot($user),
            );

            return $user->load('person', 'role');
        });
    }

    public function changeStatus(User $user, UserStatus $status): User
    {
        return DB::transaction(function () use ($user, $status) {
            if ($user->status === $status) {
                return $user->load('person', 'role');
            }

            if ($status === UserStatus::Inactive && $user->hasRole(RoleCode::Administrator)) {
                $this->ensureAnotherActiveAdministrator($user, 'status');
            }

            $before = $this->snapshot($user);
            $user->forceFill(['status' => $status])->save();

            if ($status === UserStatus::Inactive) {
                // Encerra as sessões abertas da conta (driver de sessão "database").
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            $active = $status === UserStatus::Active;

            $this->audit->record(
                $active ? AuditAction::USER_ACTIVATED : AuditAction::USER_INACTIVATED,
                'users',
                $active ? "Conta {$user->email} ativada." : "Conta {$user->email} inativada.",
                entity: $user,
                before: $before,
                after: $this->snapshot($user),
            );

            return $user->load('person', 'role');
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(User $user): array
    {
        $user->loadMissing('role');

        return [
            'person_id' => $user->person_id,
            'email' => $user->email,
            'role' => $user->role?->code?->value,
            'status' => $user->status?->value,
        ];
    }

    /**
     * Impede que o sistema fique sem nenhum Administrador ativo.
     */
    private function ensureAnotherActiveAdministrator(User $user, string $field): void
    {
        $others = User::query()
            ->whereKeyNot($user->getKey())
            ->where('status', UserStatus::Active->value)
            ->whereHas('role', fn ($query) => $query->where('code', RoleCode::Administrator->value))
            ->lockForUpdate()
            ->pluck('id')
            ->count();

        if ($others === 0) {
            throw ValidationException::withMessages([
                $field => 'Não é possível remover o último Administrador ativo.',
            ]);
        }
    }
}
