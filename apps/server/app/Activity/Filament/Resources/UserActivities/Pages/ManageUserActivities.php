<?php

namespace App\Activity\Filament\Resources\UserActivities\Pages;

use App\Activity\Filament\Resources\UserActivities\UserActivityResource;
use Filament\Resources\Pages\ListRecords;

class ManageUserActivities extends ListRecords
{
    protected static string $resource = UserActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
