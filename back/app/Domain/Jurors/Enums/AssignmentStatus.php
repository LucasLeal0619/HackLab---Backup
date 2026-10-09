<?php

namespace App\Domain\Jurors\Enums;

enum AssignmentStatus: string
{
    case Active = 'ACTIVE';
    case Revoked = 'REVOKED';
}
