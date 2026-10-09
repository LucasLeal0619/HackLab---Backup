<?php

namespace App\Policies;

use App\Domain\Users\Enums\PermissionCode;
use App\Models\EvaluationCriterion;
use App\Models\User;

class EvaluationCriterionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::EvaluationCriteriaView);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::EvaluationCriteriaManage);
    }

    public function update(User $actor, EvaluationCriterion $criterion): bool
    {
        return $actor->hasPermission(PermissionCode::EvaluationCriteriaManage);
    }

    public function changeStatus(User $actor, EvaluationCriterion $criterion): bool
    {
        return $actor->hasPermission(PermissionCode::EvaluationCriteriaManage);
    }
}
