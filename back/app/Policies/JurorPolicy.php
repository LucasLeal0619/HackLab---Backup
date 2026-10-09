<?php

namespace App\Policies;

use App\Domain\Users\Enums\PermissionCode;
use App\Models\Juror;
use App\Models\User;

class JurorPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::JurorsView);
    }

    public function view(User $actor, Juror $juror): bool
    {
        return $actor->hasPermission(PermissionCode::JurorsView);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::JurorsManage);
    }

    public function update(User $actor, Juror $juror): bool
    {
        return $actor->hasPermission(PermissionCode::JurorsManage);
    }

    public function changeStatus(User $actor, Juror $juror): bool
    {
        return $actor->hasPermission(PermissionCode::JurorsManage);
    }

    public function manageAssignments(User $actor, Juror $juror): bool
    {
        return $actor->hasPermission(PermissionCode::JurorsManage);
    }
}
