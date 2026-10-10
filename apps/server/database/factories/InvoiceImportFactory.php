<?php

namespace Database\Factories;

use App\Models\InvoiceImport;
use App\Models\Motorcycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceImport>
 */
class InvoiceImportFactory extends Factory
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
            'user_id' => fn (array $attributes) => Motorcycle::query()->whereKey(
                $attributes['motorcycle_id']
            )->firstOrFail()->user_id,
            'path' => 'test-invoice.jpg',
            'filename' => 'invoice.jpg',
            'mime' => 'image/jpeg',
            'size' => 100,
            'sha256' => hash('sha256', 'fixture'),
            'status' => 'uploaded',
            'version' => 0,
        ];
    }
}
