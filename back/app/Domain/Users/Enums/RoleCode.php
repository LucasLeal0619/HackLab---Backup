<?php

namespace App\Domain\Users\Enums;

/**
 * Os seis perfis internos do HackLab. Não existe perfil Validador (CLAUDE.md §5.2).
 */
enum RoleCode: string
{
    case Administrator = 'ADMINISTRATOR';
    case Manager = 'MANAGER';
    case Editor = 'EDITOR';
    case Consultant = 'CONSULTANT';
    case Juror = 'JUROR';
    case Voter = 'VOTER';

    /**
     * Perfis com vínculo setorial obrigatório. Os demais não têm setor (garantido também por trigger).
     */
    public function requiresSector(): bool
    {
        return in_array($this, [self::Manager, self::Editor], true);
    }

    /**
     * @return list<string>
     */
    public static function sectorRoleValues(): array
    {
        return array_values(array_map(
            fn (self $role) => $role->value,
            array_filter(self::cases(), fn (self $role) => $role->requiresSector()),
        ));
    }

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrador',
            self::Manager => 'Gestor',
            self::Editor => 'Editor',
            self::Consultant => 'Consultor',
            self::Juror => 'Jurado',
            self::Voter => 'Votante',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Administrator => 'Acesso global.',
            self::Manager => 'Lidera um setor e gerencia o próprio escopo.',
            self::Editor => 'Atua em um setor com poderes reduzidos.',
            self::Consultant => 'Visão transversal e orientação, sem administração de usuários.',
            self::Juror => 'Acessa apenas as avaliações atribuídas.',
            self::Voter => 'Acessa a votação pública quando elegível.',
        };
    }
}
