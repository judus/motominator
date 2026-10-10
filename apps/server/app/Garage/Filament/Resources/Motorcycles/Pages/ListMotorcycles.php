<?php

namespace App\Garage\Filament\Resources\Motorcycles\Pages;

use App\Garage\Filament\Resources\Motorcycles\MotorcycleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMotorcycles extends ListRecords
{
    protected static string $resource = MotorcycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
