<?php

namespace App\Policies;

use App\Domain\Users\Enums\PermissionCode;
use App\Models\Team;
use App\Models\User;

/**
 * Equipes são globais ao evento: só permissão, sem escopo setorial.
 */
class TeamPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::TeamsView);
    }

    public function view(User $actor, Team $team): bool
    {
        return $actor->hasPermission(PermissionCode::TeamsView);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::TeamsManage);
    }

    public function update(User $actor, Team $team): bool
    {
        return $actor->hasPermission(PermissionCode::TeamsManage);
    }

    /**
     * Adicionar, remover ou mover membros.
     */
    public function manageMembers(User $actor, Team $team): bool
    {
        return $actor->hasPermission(PermissionCode::TeamsManage);
    }
}
