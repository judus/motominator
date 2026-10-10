<?php

namespace App\Garage\Enums;

enum PlanStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
