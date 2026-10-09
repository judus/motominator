<?php

namespace App\Accounts\Actions;

use App\Accounts\Exceptions\AccountsSocialException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class UnlinkSocialIdentity
{
    public function __construct(
        private readonly EnsureSocialProvider $ensureSocialProvider,
        private readonly EnsureRecentPasswordConfirmation $ensureRecentPasswordConfirmation,
    ) {
    }

    public function __invoke(User $actor, string $provider, int $confirmedAt): void
    {
        ($this->ensureSocialProvider)($provider, requireConfiguration: false);
        ($this->ensureRecentPasswordConfirmation)($confirmedAt);
        DB::transaction(function () use ($actor, $provider): void {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (! hash_equals($actor->securityFingerprint(), $user->securityFingerprint())) {
                throw new AuthorizationException();
            }
            if (blank($user->password) && $user->socialIdentities()->count() <= 1) {
                throw AccountsSocialException::finalSignInMethod();
            }
            $user->socialIdentities()->where('provider', $provider)->delete();
        });
    }
}
