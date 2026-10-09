<?php

namespace App\Domain\Meetings\Enums;

enum MeetingStatus: string
{
    case Scheduled = 'SCHEDULED';
    case Done = 'DONE';
    case Cancelled = 'CANCELLED';
}
