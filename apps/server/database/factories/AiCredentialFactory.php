<?php

namespace Database\Factories;

use App\Models\AiCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiCredential> */
class AiCredentialFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'openai',
            'model' => 'test-model',
            'api_key' => 'test-key-not-a-real-secret',
            'key_hint' => '••••cret',
        ];
    }
}
