<?php

namespace App\Domain\Occurrences\Enums;

enum OccurrenceStatus: string
{
    case Open = 'OPEN';
    case InProgress = 'IN_PROGRESS';
    case Resolved = 'RESOLVED';

    public function isClosed(): bool
    {
        return $this === self::Resolved;
    }
}
