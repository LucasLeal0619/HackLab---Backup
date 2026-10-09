<?php

namespace App\Domain\Occurrences\Enums;

/**
 * Categorias do protótipo. Lista fechada também no banco (check); nova categoria = nova migration.
 */
enum OccurrenceCategory: string
{
    case Technology = 'TECHNOLOGY';
    case Infrastructure = 'INFRASTRUCTURE';
    case Production = 'PRODUCTION';
    case Participant = 'PARTICIPANT';
    case Team = 'TEAM';
    case Company = 'COMPANY';
    case Organization = 'ORGANIZATION';
    case Other = 'OTHER';
}
