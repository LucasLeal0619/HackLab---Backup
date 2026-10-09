<?php

namespace App\Policies;

use App\Domain\Users\Enums\PermissionCode;
use App\Models\Person;
use App\Models\User;

/**
 * Gestor e Editor ganham escopo setorial na Fase 2. Por ora, a permissão vale para todas as pessoas.
 */
class PersonPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::PeopleView);
    }

    public function view(User $actor, Person $person): bool
    {
        return $actor->person_id === $person->id || $actor->hasPermission(PermissionCode::PeopleView);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::PeopleManage);
    }

    public function update(User $actor, Person $person): bool
    {
        return $actor->hasPermission(PermissionCode::PeopleManage);
    }
}
