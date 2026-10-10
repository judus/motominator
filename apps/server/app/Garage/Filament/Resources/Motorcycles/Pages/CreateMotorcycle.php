<?php

namespace App\Garage\Filament\Resources\Motorcycles\Pages;

use App\Garage\Actions\SaveMotorcycle;
use App\Garage\Filament\GarageValidation;
use App\Garage\Filament\Resources\Motorcycles\MotorcycleResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMotorcycle extends CreateRecord
{
    private SaveMotorcycle $saveMotorcycle;

    public function boot(SaveMotorcycle $saveMotorcycle): void
    {
        $this->saveMotorcycle = $saveMotorcycle;
    }

    protected static string $resource = MotorcycleResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return GarageValidation::run(
            fn () => ($this->saveMotorcycle)(GarageValidation::actor(), $data, owner: User::query()->whereKey(
                $data['user_id']
            )->firstOrFail()),
            $this->form->getStatePath()
        );
    }
}
