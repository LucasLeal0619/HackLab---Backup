<?php

namespace App\Policies;

use App\Domain\Users\Enums\PermissionCode;
use App\Models\JurorTeamAssignment;
use App\Models\User;

/**
 * Escrever avaliação: permissão de avaliar + a atribuição é do jurado da MESMA Person do usuário.
 * Nem o Administrador escreve pela conta de outro jurado (só se a própria Person for jurado atribuído).
 * Estado (atribuição/jurado ativos, avaliação não enviada) é conferido no EvaluationService.
 */
class JurorTeamAssignmentPolicy
{
    public function evaluate(User $actor, JurorTeamAssignment $assignment): bool
    {
        return $actor->hasPermission(PermissionCode::EvaluationsOwn)
            && $actor->person_id === $assignment->juror?->person_id;
    }
}
