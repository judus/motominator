<?php

namespace Database\Factories;

use App\Garage\Enums\MaintenanceActionType;
use App\Models\MaintenanceAction;
use App\Models\MaintenanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceAction> */
class MaintenanceActionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'maintenance_record_id' => MaintenanceRecord::factory(),
            'type' => MaintenanceActionType::Inspect,
            'component' => 'chain',
            'position' => null,
        ];
    }
}
