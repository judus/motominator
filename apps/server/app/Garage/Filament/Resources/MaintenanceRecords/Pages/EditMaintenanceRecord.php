<?php

namespace App\Garage\Filament\Resources\MaintenanceRecords\Pages;

use App\Garage\Actions\SaveMaintenanceRecord;
use App\Garage\Filament\GarageValidation;
use App\Garage\Filament\Resources\MaintenanceRecords\MaintenanceRecordResource;
use App\Models\MaintenanceRecord;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMaintenanceRecord extends EditRecord
{
    private SaveMaintenanceRecord $saveMaintenanceRecord;

    public function boot(SaveMaintenanceRecord $saveMaintenanceRecord): void
    {
        $this->saveMaintenanceRecord = $saveMaintenanceRecord;
    }

    protected static string $resource = MaintenanceRecordResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof MaintenanceRecord);

        return GarageValidation::run(
            fn () => ($this->saveMaintenanceRecord)(GarageValidation::actor(), $record->motorcycle, $data, $record),
            $this->form->getStatePath()
        );
    }
}
