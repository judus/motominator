<?php

namespace App\Garage\Enums;

enum MaintenanceActionType: string
{
    case Inspect = 'inspect';
    case Clean = 'clean';
    case Lubricate = 'lubricate';
    case Adjust = 'adjust';
    case Repair = 'repair';
    case Replace = 'replace';
    case Other = 'other';
}
