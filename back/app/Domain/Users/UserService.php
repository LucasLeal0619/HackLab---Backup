<?php

namespace App\Domain\Users;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Occurrences\Enums\OccurrenceStatus;
use App\Domain\People\PersonService;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Users\Enums\RoleCode;
use App\Domain\Users\Enums\UserStatus;
use App\Models\Occurrence;
use App\Models\Person;
use App\Models\Role;
use App\Models\Task;
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
     *     sector_id?: ?int,
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

            $role = RoleCode::from($data['role']);

            $user = User::query()->create([
                'person_id' => $person->id,
                'role_id' => Role::forCode($role)->id,
                'sector_id' => $this->sectorFor($role, $data['sector_id'] ?? null),
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

            return $user->load('person', 'role', 'sector');
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
                return $user->load('person', 'role', 'sector');
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

            return $user->load('person', 'role', 'sector');
        });
    }

    /**
     * Troca de perfil. Gestor/Editor exigem setor; ao sair de Gestor/Editor o setor é removido.
     */
    public function changeRole(User $user, RoleCode $code, ?int $sectorId = null): User
    {
        return DB::transaction(function () use ($user, $code, $sectorId) {
            $user->loadMissing('role');
            $newSectorId = $this->sectorFor($code, $sectorId ?? ($code->requiresSector() ? $user->sector_id : null));

            if ($user->role->code === $code && $user->sector_id === $newSectorId) {
                return $user->load('person', 'role', 'sector');
            }

            if ($user->hasRole(RoleCode::Administrator) && $code !== RoleCode::Administrator) {
                $this->ensureAnotherActiveAdministrator($user, 'role');
            }

            if ($newSectorId !== $user->sector_id) {
                $this->ensureNoOpenAssignments($user, 'role');
            }

            $before = $this->snapshot($user);
            $user->forceFill([
                'role_id' => Role::forCode($code)->id,
                'sector_id' => $newSectorId,
            ])->save();
            $user->unsetRelation('role');

            $this->audit->record(
                AuditAction::USER_ROLE_CHANGED,
                'users',
                "Perfil de {$user->email} alterado de {$before['role']} para {$code->value}.",
                entity: $user,
                before: $before,
                after: $this->snapshot($user),
            );

            return $user->load('person', 'role', 'sector');
        });
    }

    /**
     * Vincula ou move Gestor/Editor de setor. Outros perfis não têm setor.
     */
    public function changeSector(User $user, int $sectorId): User
    {
        return DB::transaction(function () use ($user, $sectorId) {
            $user->loadMissing('role');

            if (! $user->role->code->requiresSector()) {
                throw ValidationException::withMessages([
                    'sector_id' => "O perfil {$user->role->name} não tem vínculo setorial.",
                ]);
            }

            if ($user->sector_id === $sectorId) {
                return $user->load('person', 'role', 'sector');
            }

            $this->ensureNoOpenAssignments($user, 'sector_id');

            $before = $this->snapshot($user);
            $user->forceFill(['sector_id' => $sectorId])->save();

            $this->audit->record(
                AuditAction::USER_SECTOR_CHANGED,
                'users',
                "Conta {$user->email} movida do setor {$before['sector_id']} para o setor {$sectorId}.",
                entity: $user,
                before: $before,
                after: $this->snapshot($user),
            );

            return $user->load('person', 'role', 'sector');
        });
    }

    public function changeStatus(User $user, UserStatus $status): User
    {
        return DB::transaction(function () use ($user, $status) {
            if ($user->status === $status) {
                return $user->load('person', 'role', 'sector');
            }

            if ($status === UserStatus::Inactive && $user->hasRole(RoleCode::Administrator)) {
                $this->ensureAnotherActiveAdministrator($user, 'status');
            }

            if ($status === UserStatus::Inactive) {
                $this->ensureNoOpenAssignments($user, 'status');
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

            return $user->load('person', 'role', 'sector');
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
            'sector_id' => $user->sector_id,
            'status' => $user->status?->value,
        ];
    }

    /**
     * Setor coerente com o perfil: obrigatório para Gestor/Editor, sempre nulo para os demais.
     */
    private function sectorFor(RoleCode $role, ?int $sectorId): ?int
    {
        if (! $role->requiresSector()) {
            return null;
        }

        if ($sectorId === null) {
            throw ValidationException::withMessages([
                'sector_id' => "O perfil {$role->label()} exige um setor.",
            ]);
        }

        return $sectorId;
    }

    /**
     * Demanda aberta atribuída a um usuário que muda de setor ou é inativado ficaria incoerente
     * (assigned_user_id de um setor, responsável de outro). Reatribua ou feche antes.
     */
    private function ensureNoOpenAssignments(User $user, string $field): void
    {
        $open = Task::query()->where('assigned_user_id', $user->id)->where('status', '!=', TaskStatus::Completed->value)->count()
            + Occurrence::query()->where('assigned_user_id', $user->id)->where('status', '!=', OccurrenceStatus::Resolved->value)->count();

        if ($open > 0) {
            throw ValidationException::withMessages([
                $field => "O usuário é responsável individual por {$open} pendência(s)/ocorrência(s) aberta(s). Reatribua ou feche antes.",
            ]);
        }
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
