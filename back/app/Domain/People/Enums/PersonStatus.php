<?php

namespace App\Domain\People\Enums;

enum PersonStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
}
