<?php

namespace App\Policies;

use App\Domain\Users\Enums\PermissionCode;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::UsersView);
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->is($user) || $actor->hasPermission(PermissionCode::UsersView);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::UsersManage);
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->hasPermission(PermissionCode::UsersManage);
    }

    public function changeRole(User $actor, User $user): bool
    {
        return $actor->hasPermission(PermissionCode::UsersManage);
    }

    public function changeStatus(User $actor, User $user): bool
    {
        return $actor->hasPermission(PermissionCode::UsersManage);
    }

    public function changeSector(User $actor, User $user): bool
    {
        return $actor->hasPermission(PermissionCode::UsersManage);
    }
}
