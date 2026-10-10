<?php

namespace App\Garage\Enums;

enum EvidenceKind: string
{
    case Invoice = 'invoice';
    case Receipt = 'receipt';
    case Photo = 'photo';
    case Other = 'other';
}
