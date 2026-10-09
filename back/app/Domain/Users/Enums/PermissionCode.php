<?php

namespace App\Domain\Users\Enums;

/**
 * Permissões iniciais (Fase 1). Novas permissões entram junto com os módulos que as usam.
 *
 * As Policies combinam a permissão com escopo (setor, vínculo, estado do recurso)
 * quando esses conceitos existirem.
 */
enum PermissionCode: string
{
    case PeopleView = 'people.view';
    case PeopleManage = 'people.manage';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case RolesView = 'roles.view';
    case AuditView = 'audit.view';

    public function label(): string
    {
        return match ($this) {
            self::PeopleView => 'Consultar pessoas',
            self::PeopleManage => 'Cadastrar e editar pessoas',
            self::UsersView => 'Consultar usuários',
            self::UsersManage => 'Cadastrar, editar, ativar/inativar usuários e alterar perfil',
            self::RolesView => 'Consultar perfis',
            self::AuditView => 'Consultar auditoria',
        };
    }

    /**
     * Matriz inicial perfil → permissões. Gestor/Editor ganham escopo setorial na Fase 2.
     *
     * @return array<string, list<self>>
     */
    public static function matrix(): array
    {
        return [
            RoleCode::Administrator->value => self::cases(),
            RoleCode::Manager->value => [self::PeopleView],
            RoleCode::Editor->value => [self::PeopleView],
            RoleCode::Consultant->value => [self::PeopleView],
            RoleCode::Juror->value => [],
            RoleCode::Voter->value => [],
        ];
    }
}
