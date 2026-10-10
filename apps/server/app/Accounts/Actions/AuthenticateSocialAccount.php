<?php

namespace App\Accounts\Actions;

use App\Models\SocialIdentity;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Two\User as OAuthUser;

class AuthenticateSocialAccount
{
    public function __construct(private readonly EnsureSocialProvider $ensureSocialProvider)
    {
    }

    /** A null result rejects registration without revealing whether the email already exists. */
    public function __invoke(string $provider, ProviderUser $external): ?User
    {
        ($this->ensureSocialProvider)($provider);
        if (blank($external->getId())) {
            return null;
        }

        try {
            return DB::transaction(function () use ($provider, $external): ?User {
                $identity = SocialIdentity::query()->where('provider', $provider)
                    ->where('provider_user_id', $external->getId())->first();
                if ($identity) {
                    return $identity->user;
                }
                $email = Str::lower($external->getEmail() ?? '');
                $verified = $provider === 'github'
                    || ($external instanceof OAuthUser && data_get($external->user, 'email_verified') === true);
                if (
                    ! config('auth.registration_enabled') || ! $verified || ! filter_var($email, FILTER_VALIDATE_EMAIL)
                    || User::query()->where('email', $email)->exists()
                ) {
                    return null;
                }
                $user = User::create([
                    'name' => Str::limit($external->getName() ?: $external->getNickname() ?: 'Rider', 255, ''),
                    'email' => $email,
                    'password' => null,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $user->socialIdentities()->create(['provider' => $provider, 'provider_user_id' => $external->getId()]);

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            return null;
        }
    }
}
