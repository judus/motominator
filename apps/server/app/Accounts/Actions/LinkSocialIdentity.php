<?php

namespace App\Accounts\Actions;

use App\Accounts\Exceptions\AccountsSocialException;
use App\Models\SocialIdentity;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LinkSocialIdentity
{
    public function __construct(
        private readonly EnsureSocialProvider $ensureSocialProvider,
        private readonly EnsureRecentPasswordConfirmation $ensureRecentPasswordConfirmation,
    ) {
    }

    public function __invoke(User $actor, string $provider, string $providerUserId, int $confirmedAt): User
    {
        ($this->ensureSocialProvider)($provider);
        ($this->ensureRecentPasswordConfirmation)($confirmedAt);
        if (blank($providerUserId)) {
            throw ValidationException::withMessages(['provider' => 'A sign-in identity is required.']);
        }

        try {
            return DB::transaction(function () use ($actor, $provider, $providerUserId): User {
                $user = User::query()->lockForUpdate()->findOrFail($actor->id);
                if (! hash_equals($actor->securityFingerprint(), $user->securityFingerprint())) {
                    throw new AuthorizationException();
                }
                $identity = SocialIdentity::query()->where('provider', $provider)
                    ->where('provider_user_id', $providerUserId)->first();
                if ($identity && $identity->user_id !== $user->id) {
                    throw AccountsSocialException::identityAlreadyLinked();
                }
                if (! $identity) {
                    $user->socialIdentities()->create(['provider' => $provider, 'provider_user_id' => $providerUserId]);
                }

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw AccountsSocialException::identityConflict($exception);
        }
    }
}
