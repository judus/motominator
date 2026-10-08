<?php

namespace App\Actions\Auth;

use App\Models\User;

class IssueDeviceToken
{
    /** @return array{token: string, expires_at: string} */
    public function __invoke(User $user, string $deviceName): array
    {
        $expiresAt = now()->addMinutes(max(1, config('auth.mobile_token_ttl_minutes')));
        $token = $user->createToken($deviceName, ['account:read'], $expiresAt);

        return ['token' => $token->plainTextToken, 'expires_at' => $expiresAt->toIso8601String()];
    }
}
