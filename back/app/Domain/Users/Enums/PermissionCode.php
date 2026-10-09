<?php

namespace App\Domain\Users\Enums;

/**
 * Permissões por perfil. Novas permissões entram junto com os módulos que as usam.
 *
 * A Policy combina a permissão com o escopo do usuário (User::canReachSector):
 * quem tem setor (Gestor, Editor) fica limitado a ele; quem não tem setor tem
 * alcance global dentro do que as permissões permitem.
 */
enum PermissionCode: string
{
    case PeopleView = 'people.view';
    case PeopleManage = 'people.manage';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case RolesView = 'roles.view';
    case AuditView = 'audit.view';
    case EventsView = 'events.view';
    case EventsManage = 'events.manage';
    case SectorsView = 'sectors.view';
    case SectorsManage = 'sectors.manage';
    case MeetingsView = 'meetings.view';
    case MeetingsManage = 'meetings.manage';
    case ClassesView = 'classes.view';
    case ClassesManage = 'classes.manage';
    case ParticipantsView = 'participants.view';
    case ParticipantsManage = 'participants.manage';
    case TeamsView = 'teams.view';
    case TeamsManage = 'teams.manage';

    public function label(): string
    {
        return match ($this) {
            self::PeopleView => 'Consultar pessoas',
            self::PeopleManage => 'Cadastrar e editar pessoas',
            self::UsersView => 'Consultar usuários',
            self::UsersManage => 'Cadastrar, editar, ativar/inativar usuários e alterar perfil',
            self::RolesView => 'Consultar perfis',
            self::AuditView => 'Consultar auditoria',
            self::EventsView => 'Consultar eventos e dias',
            self::EventsManage => 'Cadastrar e editar eventos e dias',
            self::SectorsView => 'Consultar setores (no escopo do usuário)',
            self::SectorsManage => 'Gerenciar setores (no escopo do usuário)',
            self::MeetingsView => 'Consultar reuniões (no escopo do usuário)',
            self::MeetingsManage => 'Gerenciar reuniões (no escopo do usuário)',
            self::ClassesView => 'Consultar turmas',
            self::ClassesManage => 'Cadastrar, editar, ativar/inativar turmas',
            self::ParticipantsView => 'Consultar participantes',
            self::ParticipantsManage => 'Cadastrar e editar participantes',
            self::TeamsView => 'Consultar equipes e composição',
            self::TeamsManage => 'Cadastrar equipes e gerenciar membros',
        };
    }

    /**
     * Matriz perfil → permissões.
     *
     * @return array<string, list<self>>
     */
    public static function matrix(): array
    {
        return [
            RoleCode::Administrator->value => self::cases(),
            RoleCode::Manager->value => [
                self::PeopleView, self::EventsView,
                self::SectorsView, self::SectorsManage,
                self::MeetingsView, self::MeetingsManage,
                self::ClassesView, self::ParticipantsView, self::TeamsView,
            ],
            RoleCode::Editor->value => [
                self::PeopleView, self::EventsView, self::SectorsView, self::MeetingsView,
                self::ClassesView, self::ParticipantsView, self::TeamsView,
            ],
            RoleCode::Consultant->value => [
                self::PeopleView, self::EventsView, self::SectorsView, self::MeetingsView,
                self::ClassesView, self::ParticipantsView, self::TeamsView,
            ],
            RoleCode::Juror->value => [self::EventsView],
            RoleCode::Voter->value => [self::EventsView],
        ];
    }
}
