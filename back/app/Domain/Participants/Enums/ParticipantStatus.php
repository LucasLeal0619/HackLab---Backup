<?php

namespace App\Domain\Participants\Enums;

/**
 * Mudar o status não mexe no vínculo com equipe: remover/mover membro é ação explícita.
 */
enum ParticipantStatus: string
{
    case Available = 'AVAILABLE';
    case Unavailable = 'UNAVAILABLE';
    case Withdrawn = 'WITHDRAWN';
}
