<?php

namespace App\Domain\Demands\Enums;

enum DemandPriority: string
{
    case Low = 'LOW';
    case Medium = 'MEDIUM';
    case High = 'HIGH';
    case Urgent = 'URGENT';
}
