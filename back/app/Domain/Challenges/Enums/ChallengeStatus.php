<?php

namespace App\Domain\Challenges\Enums;

/**
 * Rascunho → Recebido → Em análise → Aprovado → Distribuído → Em desenvolvimento → Finalizado.
 */
enum ChallengeStatus: string
{
    case Draft = 'DRAFT';
    case Received = 'RECEIVED';
    case UnderReview = 'UNDER_REVIEW';
    case Approved = 'APPROVED';
    case Distributed = 'DISTRIBUTED';
    case InDevelopment = 'IN_DEVELOPMENT';
    case Finished = 'FINISHED';

    /**
     * Status que exigem equipe vinculada.
     */
    public function requiresTeam(): bool
    {
        return in_array($this, [self::Distributed, self::InDevelopment], true);
    }

    /**
     * Status em que uma equipe pode estar vinculada.
     */
    public function allowsTeam(): bool
    {
        return in_array($this, [self::Distributed, self::InDevelopment, self::Finished], true);
    }

    /**
     * Status em que a equipe não pode ser retirada nem trocada sem antes mudar o status.
     */
    public function locksTeam(): bool
    {
        return in_array($this, [self::InDevelopment, self::Finished], true);
    }

    /**
     * Status aceitos na criação (antes da distribuição).
     *
     * @return list<string>
     */
    public static function creatableValues(): array
    {
        return [self::Draft->value, self::Received->value, self::UnderReview->value, self::Approved->value];
    }
}
