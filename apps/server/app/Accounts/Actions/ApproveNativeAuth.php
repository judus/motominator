<?php

namespace App\Accounts\Actions;

use App\Accounts\Data\NativeAuthIntent;
use App\Accounts\Exceptions\AccountsNativeAuthException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ApproveNativeAuth
{
    public function __invoke(User $actor, int $expectedUserId, string $code, ?string $securityFingerprint = null): void
    {
        if ($actor->id !== $expectedUserId) {
            throw new AuthorizationException();
        }
        $key = 'native-auth:' . hash('sha256', $code);
        Cache::lock($key . ':lock', 10)->block(3, function () use ($key, $actor, $securityFingerprint): void {
            $intent = NativeAuthIntent::fromCache(Cache::get($key));
            if ($intent === null || $intent->expiresAt <= now()->getTimestamp()) {
                throw AccountsNativeAuthException::expiredIntent();
            }
            $fingerprint = DB::transaction(function () use ($actor, $securityFingerprint): string {
                $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
                $current = $fresh->securityFingerprint(includeRecoveryCodes: false);
                if (
                    ! hash_equals(
                        $securityFingerprint ?? $actor->securityFingerprint(includeRecoveryCodes: false),
                        $current
                    )
                ) {
                    throw AccountsNativeAuthException::invalidExchange();
                }

                return $fresh->securityFingerprint();
            });
            $approved = new NativeAuthIntent(
                $intent->provider,
                $intent->challenge,
                $intent->deviceName,
                $intent->expiresAt,
                $actor->id,
                $fingerprint,
            );
            Cache::put($key, $approved->toArray(), now()->addSeconds($intent->expiresAt - now()->getTimestamp()));
        });
    }
}
