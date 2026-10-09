<?php

namespace App\Domain\Teams\Enums;

enum TeamStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
}
