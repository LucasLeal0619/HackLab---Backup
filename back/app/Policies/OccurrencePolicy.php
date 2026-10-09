<?php

namespace App\Policies;

use App\Domain\Users\Enums\PermissionCode;
use App\Models\Occurrence;
use App\Models\User;

class OccurrencePolicy extends DemandPolicy
{
    protected function prefix(): string
    {
        return 'occurrences';
    }

    /**
     * Gerar pendência a partir da ocorrência: Administrador ou Gestor de setor relacionado
     * (tasks.create + occurrences.route). Editor e Consultor não geram.
     */
    public function generateTask(User $actor, Occurrence $occurrence): bool
    {
        return $actor->hasPermission(PermissionCode::TasksCreate)
            && $actor->hasPermission(PermissionCode::OccurrencesRoute)
            && $this->participates($actor, $occurrence);
    }
}
