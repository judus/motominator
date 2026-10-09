<?php

namespace App\Accounts\Actions;

use App\Accounts\Data\NativeLinkIntent;
use App\Accounts\Exceptions\AccountsNativeAuthException;
use Illuminate\Support\Facades\Cache;

class CompleteNativeSocialLink
{
    public function __invoke(string $code, string $provider, string $providerUserId): void
    {
        $key = 'native-link:' . hash('sha256', $code);
        Cache::lock($key . ':lock', 10)->block(3, function () use ($key, $provider, $providerUserId): void {
            $intent = NativeLinkIntent::fromCache(Cache::get($key));
            if (
                $intent === null || $intent->expiresAt <= now()->getTimestamp()
                || $intent->provider !== $provider || $intent->providerUserId !== null
            ) {
                throw AccountsNativeAuthException::expiredIntent();
            }
            $completed = new NativeLinkIntent(
                $intent->userId,
                $intent->provider,
                $intent->challenge,
                $intent->confirmedAt,
                $intent->expiresAt,
                $intent->securityFingerprint,
                $providerUserId,
            );
            Cache::put($key, $completed->toArray(), now()->addSeconds($intent->expiresAt - now()->getTimestamp()));
        });
    }
}
