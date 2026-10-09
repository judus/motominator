<?php

namespace Database\Factories;

use App\Garage\Enums\HistoryOrigin;
use App\Garage\Enums\MileageUnit;
use App\Garage\Enums\ReadingCertainty;
use App\Models\MileageReading;
use App\Models\Motorcycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MileageReading> */
class MileageReadingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'motorcycle_id' => Motorcycle::factory(),
            'observed_on' => '2025-06-01',
            'odometer_value' => '49500.000',
            'unit' => MileageUnit::Kilometers,
            'certainty' => ReadingCertainty::Exact,
            'origin' => HistoryOrigin::Manual,
        ];
    }
}
