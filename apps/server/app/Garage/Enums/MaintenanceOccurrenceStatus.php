<?php

namespace App\Garage\Enums;

enum MaintenanceOccurrenceStatus: string
{
    case Open = 'open';
    case Partial = 'partial';
    case Completed = 'completed';
    case Skipped = 'skipped';
}
