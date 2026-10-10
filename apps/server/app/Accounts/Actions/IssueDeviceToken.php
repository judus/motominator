<?php

namespace App\Accounts\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Config;

class IssueDeviceToken
{
    /** @return array{token: string, expires_at: string} */
    public function __invoke(User $user, string $deviceName): array
    {
        $expiresAt = now()->addMinutes(
            max(1, Config::integer('auth.mobile_token_ttl_minutes'))
        );
        $token = $user->createToken(
            $deviceName,
            ['account:read', 'account:write', 'garage:read', 'garage:write', 'ai:read', 'ai:write'],
            $expiresAt
        );

        return ['token' => $token->plainTextToken, 'expires_at' => $expiresAt->toIso8601String()];
    }
}
