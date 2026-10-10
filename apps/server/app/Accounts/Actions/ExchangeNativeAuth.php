<?php

namespace App\Accounts\Actions;

use App\Accounts\Data\NativeAuthIntent;
use App\Accounts\Exceptions\AccountsNativeAuthException;
use App\Models\User;
use App\Support\Input;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExchangeNativeAuth
{
    public function __construct(private readonly IssueDeviceToken $issueDeviceToken)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{token: string, expires_at: string}
     */
    public function __invoke(array $input): array
    {
        $data = Input::object(Validator::make($input, [
            'code' => ['required', 'string', 'size:64'],
            'verifier' => ['required', 'string', 'size:64'],
        ])->validate());
        $key = 'native-auth:' . hash('sha256', Input::string($data['code'], 'code'));
        $verifier = Input::string($data['verifier'], 'verifier');

        return Cache::lock($key . ':lock', 10)->block(3, function () use ($key, $verifier): array {
            $intent = NativeAuthIntent::fromCache(Cache::get($key));
            if (
                $intent === null || $intent->expiresAt <= now()->getTimestamp() || $intent->userId === null
                || ! hash_equals($intent->challenge, hash('sha256', $verifier))
            ) {
                throw AccountsNativeAuthException::invalidExchange();
            }
            $result = DB::transaction(function () use ($intent): array {
                $user = User::query()->lockForUpdate()->findOrFail($intent->userId);
                if (
                    $intent->securityFingerprint === null
                    || ! hash_equals($intent->securityFingerprint, $user->securityFingerprint())
                ) {
                    throw AccountsNativeAuthException::invalidExchange();
                }

                return ($this->issueDeviceToken)($user, $intent->deviceName);
            });
            Cache::forget($key);

            return $result;
        });
    }
}
