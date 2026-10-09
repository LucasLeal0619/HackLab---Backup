<?php

namespace App\Domain\Events\Enums;

enum EventStatus: string
{
    case Planned = 'PLANNED';
    case Active = 'ACTIVE';
    case Finished = 'FINISHED';
    case Cancelled = 'CANCELLED';
}
