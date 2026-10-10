<?php

namespace Database\Factories;

use App\Models\InvoiceImport;
use App\Models\MaintenanceInvoice;
use App\Models\MaintenanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceInvoice>
 */
class MaintenanceInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_import_id' => InvoiceImport::factory(),
            'maintenance_record_id' => fn (array $attributes) => MaintenanceRecord::factory()->create(
                [
                        'motorcycle_id' => InvoiceImport::query()->whereKey(
                            $attributes['invoice_import_id']
                        )->firstOrFail()->motorcycle_id
                    ]
            )->id,
            'total_amount' => '75.20',
            'currency' => 'CHF',
        ];
    }
}
