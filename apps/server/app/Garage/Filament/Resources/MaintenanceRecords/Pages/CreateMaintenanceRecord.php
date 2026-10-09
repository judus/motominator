<?php

namespace App\Garage\Filament\Resources\MaintenanceRecords\Pages;

use App\Garage\Actions\SaveMaintenanceRecord;
use App\Garage\Filament\GarageValidation;
use App\Garage\Filament\Resources\MaintenanceRecords\MaintenanceRecordResource;
use App\Models\Motorcycle;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMaintenanceRecord extends CreateRecord
{
    private SaveMaintenanceRecord $saveMaintenanceRecord;

    public function boot(SaveMaintenanceRecord $saveMaintenanceRecord): void
    {
        $this->saveMaintenanceRecord = $saveMaintenanceRecord;
    }

    protected static string $resource = MaintenanceRecordResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return GarageValidation::run(
            fn () => ($this->saveMaintenanceRecord)(GarageValidation::actor(), Motorcycle::query()->whereKey(
                $data['motorcycle_id']
            )->firstOrFail(), $data),
            $this->form->getStatePath()
        );
    }
}
