<?php

namespace Database\Factories;

use App\Garage\Enums\MaintenanceActionType;
use App\Garage\Enums\MaintenanceScheduleKind;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceTask> */
class MaintenanceTaskFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'maintenance_plan_id' => MaintenancePlan::factory(),
            'title' => 'Inspect chain',
            'type' => MaintenanceActionType::Inspect,
            'component' => 'chain',
            'position' => null,
            'schedule_kind' => MaintenanceScheduleKind::Manual,
        ];
    }
}
