<?php

namespace Database\Factories;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Motorcycle>
 */
class MotorcycleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'make' => 'Honda',
            'model' => 'CB500X',
            'year' => 2022,
            'nickname' => null,
            'odometer_km' => 10000,
        ];
    }
}
