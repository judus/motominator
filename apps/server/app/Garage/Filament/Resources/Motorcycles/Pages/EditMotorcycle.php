<?php

namespace App\Garage\Filament\Resources\Motorcycles\Pages;

use App\Garage\Actions\SaveMotorcycle;
use App\Garage\Filament\GarageValidation;
use App\Garage\Filament\Resources\Motorcycles\MotorcycleResource;
use App\Models\Motorcycle;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMotorcycle extends EditRecord
{
    private SaveMotorcycle $saveMotorcycle;

    public function boot(SaveMotorcycle $saveMotorcycle): void
    {
        $this->saveMotorcycle = $saveMotorcycle;
    }

    protected static string $resource = MotorcycleResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof Motorcycle);

        return GarageValidation::run(
            fn () => ($this->saveMotorcycle)(GarageValidation::actor(), $data, $record),
            $this->form->getStatePath()
        );
    }
}
