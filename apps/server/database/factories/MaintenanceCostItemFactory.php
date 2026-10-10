<?php

namespace Database\Factories;

use App\Models\MaintenanceCostItem;
use App\Models\MaintenanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceCostItem> */
class MaintenanceCostItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'maintenance_record_id' => MaintenanceRecord::factory(),
            'position' => 0,
            'description' => 'Chain lubricant',
            'quantity' => '1.0000',
            'unit_price' => '12.5000',
            'total_amount' => '12.50',
            'currency' => 'CHF',
        ];
    }
}
