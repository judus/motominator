<?php

namespace App\Accounts\Actions;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class RevokeDeviceToken
{
    public function __construct(private readonly EnsureRecentPasswordConfirmation $ensureRecentPasswordConfirmation)
    {
    }

    public function __invoke(User $actor, int $tokenId, int $confirmedAt): void
    {
        ($this->ensureRecentPasswordConfirmation)($confirmedAt);
        DB::transaction(function () use ($actor, $tokenId): void {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (! hash_equals($actor->securityFingerprint(), $user->securityFingerprint())) {
                throw new AuthorizationException();
            }
            $user->tokens()->findOrFail($tokenId)->delete();
        });
    }
}
