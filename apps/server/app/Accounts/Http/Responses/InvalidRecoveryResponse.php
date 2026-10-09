<?php

namespace App\Accounts\Http\Responses;

use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;

class InvalidRecoveryResponse implements FailedPasswordResetResponse
{
    public function toResponse($request)
    {
        throw ValidationException::withMessages([
            'email' => ['This password reset link is invalid or expired. Please request a new link.'],
        ]);
    }
}
