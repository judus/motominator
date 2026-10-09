<?php

namespace App\Accounts\Actions;

use App\Accounts\Data\NativeAuthIntent;
use App\Support\Input;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StartNativeAuth
{
    public function __construct(private readonly EnsureSocialProvider $ensureSocialProvider)
    {
    }

    /** @param array<string, mixed> $input */
    public function __invoke(array $input): string
    {
        $data = Input::object(Validator::make($input, [
            'provider' => ['required', 'in:google,github'],
            'challenge' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'device_name' => ['required', 'string', 'max:100'],
        ])->validate());
        $provider = Input::string($data['provider'], 'provider');
        ($this->ensureSocialProvider)($provider);
        $code = Str::random(64);
        $expiresAt = now()->addMinutes(5);
        $intent = new NativeAuthIntent(
            $provider,
            Input::string($data['challenge'], 'challenge'),
            Input::string($data['device_name'], 'device_name'),
            $expiresAt->getTimestamp(),
            null,
        );
        Cache::put('native-auth:' . hash('sha256', $code), $intent->toArray(), $expiresAt);

        return $code;
    }
}
