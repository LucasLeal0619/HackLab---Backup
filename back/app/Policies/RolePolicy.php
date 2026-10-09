<?php

namespace App\Policies;

use App\Domain\Users\Enums\PermissionCode;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::RolesView);
    }
}
