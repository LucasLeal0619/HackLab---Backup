<?php

namespace App\Policies;

use App\Domain\Users\Enums\PermissionCode;
use App\Models\Company;
use App\Models\User;

/**
 * Empresas (e seus representantes) são globais ao evento: só permissão, sem escopo setorial.
 */
class CompanyPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::CompaniesView);
    }

    public function view(User $actor, Company $company): bool
    {
        return $actor->hasPermission(PermissionCode::CompaniesView);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(PermissionCode::CompaniesManage);
    }

    public function update(User $actor, Company $company): bool
    {
        return $actor->hasPermission(PermissionCode::CompaniesManage);
    }

    public function changeStatus(User $actor, Company $company): bool
    {
        return $actor->hasPermission(PermissionCode::CompaniesManage);
    }

    /**
     * Adicionar, editar e ativar/desativar representantes.
     */
    public function manageRepresentatives(User $actor, Company $company): bool
    {
        return $actor->hasPermission(PermissionCode::CompaniesManage);
    }
}
