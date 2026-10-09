<?php

namespace App\Policies;

use App\Domain\Users\Enums\PermissionCode;
use App\Models\Participant;
use App\Models\User;

/**
 * Participantes são globais ao evento: só permissão, sem escopo setorial.
 */
class ParticipantPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::ParticipantsView);
    }

    public function view(User $actor, Participant $participant): bool
    {
        return $actor->hasPermission(PermissionCode::ParticipantsView);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::ParticipantsManage);
    }

    public function update(User $actor, Participant $participant): bool
    {
        return $actor->hasPermission(PermissionCode::ParticipantsManage);
    }
}
