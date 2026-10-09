<?php

namespace App\Policies;

use App\Domain\Users\Enums\PermissionCode;
use App\Models\Challenge;
use App\Models\User;

/**
 * Desafios são globais ao evento: só permissão, sem escopo setorial.
 */
class ChallengePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::ChallengesView);
    }

    public function view(User $actor, Challenge $challenge): bool
    {
        return $actor->hasPermission(PermissionCode::ChallengesView);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::ChallengesManage);
    }

    public function update(User $actor, Challenge $challenge): bool
    {
        return $actor->hasPermission(PermissionCode::ChallengesManage);
    }

    public function changeStatus(User $actor, Challenge $challenge): bool
    {
        return $actor->hasPermission(PermissionCode::ChallengesManage);
    }

    /**
     * Distribuir, mover ou retirar a equipe do desafio.
     */
    public function assignTeam(User $actor, Challenge $challenge): bool
    {
        return $actor->hasPermission(PermissionCode::ChallengesManage);
    }
}
