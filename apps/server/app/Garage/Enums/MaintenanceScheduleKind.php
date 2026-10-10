<?php

namespace App\Garage\Enums;

enum MaintenanceScheduleKind: string
{
    case Manual = 'manual';
    case Recurring = 'recurring';
    case Milestone = 'milestone';
    case Condition = 'condition';
}
