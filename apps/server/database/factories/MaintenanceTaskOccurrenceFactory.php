<?php

namespace Database\Factories;

use App\Garage\Enums\MaintenanceOccurrenceStatus;
use App\Models\MaintenanceTask;
use App\Models\MaintenanceTaskOccurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceTaskOccurrence> */
class MaintenanceTaskOccurrenceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'maintenance_task_id' => MaintenanceTask::factory(),
            'status' => MaintenanceOccurrenceStatus::Open,
        ];
    }
}
