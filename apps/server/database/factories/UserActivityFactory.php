<?php

namespace Database\Factories;

use App\Activity\Enums\ActivityEvent;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserActivity> */
class UserActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'actor_id' => null,
            'event' => ActivityEvent::MotorcycleCreated,
            'subject_type' => 'motorcycle',
            'subject_id' => 1,
            'source' => 'system',
            'trace_id' => null,
            'changes' => ['make' => ['before' => null, 'after' => 'Honda']],
        ];
    }
}
