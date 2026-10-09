<?php

namespace App\Policies;

use App\Domain\Users\Enums\PermissionCode;
use App\Models\Meeting;
use App\Models\User;

/**
 * Reuniões gerais (sem setor): visíveis a quem tem meetings.view; criadas/editadas só com escopo global.
 * Reuniões setoriais: ver e gerenciar exigem alcançar o setor da reunião.
 */
class MeetingPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::MeetingsView);
    }

    public function view(User $actor, Meeting $meeting): bool
    {
        if (! $actor->hasPermission(PermissionCode::MeetingsView)) {
            return false;
        }

        return $meeting->isGeneral() || $actor->canReachSector($meeting->sector_id);
    }

    /**
     * @param  int|null  $sectorId  setor da reunião a criar (null = geral)
     */
    public function create(User $actor, ?int $sectorId = null): bool
    {
        return $this->canManageIn($actor, $sectorId);
    }

    /**
     * Para mover a reunião de setor, o controller também checa create() no setor de destino.
     */
    public function update(User $actor, Meeting $meeting): bool
    {
        return $this->canManageIn($actor, $meeting->sector_id);
    }

    private function canManageIn(User $actor, ?int $sectorId): bool
    {
        if (! $actor->hasPermission(PermissionCode::MeetingsManage)) {
            return false;
        }

        return $sectorId === null ? $actor->reachesAllSectors() : $actor->canReachSector($sectorId);
    }
}
