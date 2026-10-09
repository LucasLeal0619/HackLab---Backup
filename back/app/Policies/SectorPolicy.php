<?php

namespace App\Policies;

use App\Domain\Users\Enums\PermissionCode;
use App\Models\Sector;
use App\Models\User;

/**
 * Permissão + escopo: quem tem setor só alcança o próprio; criar e ativar/inativar setores é global.
 */
class SectorPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::SectorsView);
    }

    public function view(User $actor, Sector $sector): bool
    {
        return $actor->hasPermission(PermissionCode::SectorsView) && $actor->canReachSector($sector->id);
    }

    public function create(User $actor): bool
    {
        return $actor->reachesAllSectors() && $actor->hasPermission(PermissionCode::SectorsManage);
    }

    public function update(User $actor, Sector $sector): bool
    {
        return $actor->hasPermission(PermissionCode::SectorsManage) && $actor->canReachSector($sector->id);
    }

    public function changeStatus(User $actor, Sector $sector): bool
    {
        return $actor->reachesAllSectors() && $actor->hasPermission(PermissionCode::SectorsManage);
    }
}
