<?php

namespace App\Accounts\Actions;

use App\Accounts\Data\NativeLinkIntent;
use App\Accounts\Exceptions\AccountsNativeAuthException;
use App\Models\User;
use App\Support\Input;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ConsumeNativeSocialLink
{
    public function __construct(private readonly LinkSocialIdentity $link)
    {
    }

    /** @param array<string, mixed> $input */
    public function __invoke(User $actor, array $input): void
    {
        $data = Input::object(Validator::make($input, [
            'code' => ['required', 'string', 'size:64'],
            'verifier' => ['required', 'string', 'size:64'],
        ])->validate());
        $key = 'native-link:' . hash('sha256', Input::string($data['code'], 'code'));
        $verifier = Input::string($data['verifier'], 'verifier');
        Cache::lock($key . ':lock', 10)->block(3, function () use ($actor, $key, $verifier): void {
            $intent = NativeLinkIntent::fromCache(Cache::get($key));
            if (
                $intent === null || $intent->providerUserId === null || $intent->expiresAt <= now()->timestamp
                || $intent->userId !== $actor->id || ! hash_equals($intent->challenge, hash('sha256', $verifier))
            ) {
                throw AccountsNativeAuthException::invalidExchange();
            }
            DB::transaction(function () use ($actor, $intent): void {
                $user = User::query()->lockForUpdate()->findOrFail($actor->id);
                if (! hash_equals($intent->securityFingerprint, $user->securityFingerprint())) {
                    throw AccountsNativeAuthException::invalidExchange();
                }
                ($this->link)($user, $intent->provider, $intent->providerUserId, $intent->confirmedAt);
            });
            Cache::forget($key);
        });
    }
}
