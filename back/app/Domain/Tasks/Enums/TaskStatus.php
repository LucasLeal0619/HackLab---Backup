<?php

namespace App\Domain\Tasks\Enums;

enum TaskStatus: string
{
    case Pending = 'PENDING';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';

    public function isClosed(): bool
    {
        return $this === self::Completed;
    }
}
