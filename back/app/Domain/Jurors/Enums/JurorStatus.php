<?php

namespace App\Domain\Jurors\Enums;

enum JurorStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
}
