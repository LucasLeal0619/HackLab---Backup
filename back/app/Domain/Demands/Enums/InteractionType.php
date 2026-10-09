<?php

namespace App\Domain\Demands\Enums;

/**
 * Tipos de interação do histórico contextual de pendências e ocorrências.
 */
enum InteractionType: string
{
    case Created = 'CREATED';
    case Updated = 'UPDATED';
    case Comment = 'COMMENT';
    case StatusChanged = 'STATUS_CHANGED';
    case Forwarded = 'FORWARDED';
    case AssigneeChanged = 'ASSIGNEE_CHANGED';
    case PriorityChanged = 'PRIORITY_CHANGED';
    case DueChanged = 'DUE_CHANGED';
    case SectorAdded = 'SECTOR_ADDED';
    case SectorRemoved = 'SECTOR_REMOVED';
    case Completed = 'COMPLETED';
    case Resolved = 'RESOLVED';
    case Reopened = 'REOPENED';
    case TaskGenerated = 'TASK_GENERATED';
}
