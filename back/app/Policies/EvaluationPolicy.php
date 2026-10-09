<?php

namespace App\Policies;

use App\Domain\Users\Enums\PermissionCode;
use App\Models\Evaluation;
use App\Models\User;

/**
 * Leitura de avaliações: o próprio jurado vê a sua; com evaluations.all.view, todas (Administrador).
 * Gestor/Consultor só veem progresso agregado (viewProgress), sem notas nem comentários.
 */
class EvaluationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::EvaluationsAllView);
    }

    public function view(User $actor, Evaluation $evaluation): bool
    {
        if ($actor->hasPermission(PermissionCode::EvaluationsAllView)) {
            return true;
        }

        return $actor->hasPermission(PermissionCode::EvaluationsOwn)
            && $evaluation->juror?->person_id === $actor->person_id;
    }

    public function viewProgress(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::EvaluationsProgressView);
    }

    public function viewTechnicalResults(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::EvaluationsAllView);
    }

    public function requestRevision(User $actor, Evaluation $evaluation): bool
    {
        return $actor->hasPermission(PermissionCode::EvaluationsRequestRevision);
    }

    /**
     * Área "minhas avaliações": quem tem a permissão de avaliar (o vínculo com Juror é por Person).
     */
    public function viewOwn(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::EvaluationsOwn);
    }
}
