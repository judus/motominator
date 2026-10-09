<?php

namespace App\Garage\Enums;

enum MaintenanceRuleSource: string
{
    case User = 'user';
    case Manufacturer = 'manufacturer';
    case Workshop = 'workshop';
}
