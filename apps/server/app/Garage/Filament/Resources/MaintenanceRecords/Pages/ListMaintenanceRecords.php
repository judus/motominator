<?php

namespace App\Garage\Filament\Resources\MaintenanceRecords\Pages;

use App\Garage\Filament\Resources\MaintenanceRecords\MaintenanceRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceRecords extends ListRecords
{
    protected static string $resource = MaintenanceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
