<?php

namespace App\Accounts\Actions;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Sanctum\Contracts\HasAbilities;
use Laravel\Sanctum\PersonalAccessToken;

class RevokeCurrentDeviceToken
{
    public function __invoke(User $actor, HasAbilities $token): void
    {
        if (! $token instanceof PersonalAccessToken) {
            throw new AuthorizationException('This endpoint requires a device token.');
        }
        $actor->tokens()->findOrFail($token->id)->delete();
    }
}
