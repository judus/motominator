<?php

namespace App\Accounts\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Config;

class EnsureRecentPasswordConfirmation
{
    public function __invoke(int $confirmedAt): void
    {
        if (now()->getTimestamp() - $confirmedAt >= Config::integer('auth.password_timeout')) {
            throw new AuthorizationException();
        }
    }
}
