<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Usuário logado (login e /auth/me): inclui as permissões efetivas para o front montar menus.
 * O front usa isso só para exibição; a autorização real é sempre do backend.
 */
class AuthenticatedUserResource extends UserResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'permissions' => $this->resource->permissionCodes(),
        ];
    }
}
