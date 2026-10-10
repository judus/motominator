<?php

namespace App\Garage\Enums;

enum HistoryOrigin: string
{
    case Unknown = 'unknown';
    case Manual = 'manual';
    case Invoice = 'invoice';
}
