<?php

namespace App\Garage\Enums;

enum MaintenancePerformer: string
{
    case Unknown = 'unknown';
    case Self = 'self';
    case Workshop = 'workshop';
    case Other = 'other';
}
