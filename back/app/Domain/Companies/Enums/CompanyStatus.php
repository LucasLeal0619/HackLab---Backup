<?php

namespace App\Domain\Companies\Enums;

/**
 * "Aguardando desafio" e "Com desafio" não são status: são derivados dos desafios.
 */
enum CompanyStatus: string
{
    case Draft = 'DRAFT';
    case Confirmed = 'CONFIRMED';
    case Inactive = 'INACTIVE';
}
