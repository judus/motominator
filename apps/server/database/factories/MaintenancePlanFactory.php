<?php

namespace Database\Factories;

use App\Garage\Enums\MaintenanceRuleSource;
use App\Garage\Enums\PlanStatus;
use App\Models\MaintenancePlan;
use App\Models\Motorcycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenancePlan> */
class MaintenancePlanFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'motorcycle_id' => Motorcycle::factory(),
            'name' => 'My maintenance plan',
            'status' => PlanStatus::Draft,
            'source' => MaintenanceRuleSource::User,
        ];
    }
}
