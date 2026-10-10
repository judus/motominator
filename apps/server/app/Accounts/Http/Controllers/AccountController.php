<?php

namespace App\Accounts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /** @return array<string, mixed> */
    public function show(Request $request): array
    {
        $user = $this->authenticatedUser($request);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'two_factor_pending' => filled(
                $user->two_factor_secret
            ) && ! $user->hasEnabledTwoFactorAuthentication(),
            'providers' => $user->socialIdentities()->pluck('provider')->all(),
        ];
    }
}
