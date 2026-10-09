<?php

namespace Database\Factories;

use App\Models\MaintenanceInvoice;
use App\Models\MaintenanceInvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceInvoiceItem>
 */
class MaintenanceInvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'maintenance_invoice_id' => MaintenanceInvoice::factory(),
            'position' => 0,
            'description' => 'Oil filter',
            'quantity' => '1.0000',
            'unit_price' => '75.2000',
            'total_amount' => '75.20',
        ];
    }
}
