<?php

namespace App\Accounts\Filament\Resources\Users\Pages;

use App\Accounts\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
