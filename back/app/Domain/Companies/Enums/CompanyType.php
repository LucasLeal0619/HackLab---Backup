<?php

namespace App\Domain\Companies\Enums;

enum CompanyType: string
{
    case Participant = 'PARTICIPANT';
    case Partner = 'PARTNER';
    case Sponsor = 'SPONSOR';
    case Support = 'SUPPORT';
    case Other = 'OTHER';
}
