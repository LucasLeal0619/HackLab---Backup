<?php

namespace App\Policies;

use App\Domain\Users\Enums\PermissionCode;
use App\Models\SchoolClass;
use App\Models\User;

/**
 * Turmas são globais ao evento: só permissão, sem escopo setorial.
 */
class SchoolClassPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::ClassesView);
    }

    public function view(User $actor, SchoolClass $class): bool
    {
        return $actor->hasPermission(PermissionCode::ClassesView);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::ClassesManage);
    }

    public function update(User $actor, SchoolClass $class): bool
    {
        return $actor->hasPermission(PermissionCode::ClassesManage);
    }

    public function changeStatus(User $actor, SchoolClass $class): bool
    {
        return $actor->hasPermission(PermissionCode::ClassesManage);
    }
}
