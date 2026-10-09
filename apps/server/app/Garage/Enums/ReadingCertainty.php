<?php

namespace App\Garage\Enums;

enum ReadingCertainty: string
{
    case Exact = 'exact';
    case Approximate = 'approximate';
}
