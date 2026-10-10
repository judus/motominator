<?php

namespace Database\Factories;

use App\Models\MaintenanceRecord;
use App\Models\Motorcycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRecord>
 */
class MaintenanceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'motorcycle_id' => Motorcycle::factory(),
            'performed_on' => '2025-06-01',
            'title' => 'Oil change',
            'odometer_km' => 9000,
            'notes' => null,
            'cost_amount' => null,
            'currency' => null,
        ];
    }
}
