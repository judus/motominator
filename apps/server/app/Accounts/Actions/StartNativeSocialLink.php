<?php

namespace App\Accounts\Actions;

use App\Accounts\Data\NativeLinkIntent;
use App\Models\User;
use App\Support\Input;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StartNativeSocialLink
{
    public function __construct(
        private readonly ConfirmAccountPassword $confirmPassword,
        private readonly EnsureSocialProvider $ensureProvider,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function __invoke(User $actor, array $input): string
    {
        $data = Input::object(Validator::make($input, [
            'provider' => ['required', 'in:google,github'],
            'password' => ['required', 'string'],
            'challenge' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ])->validate());
        $confirmedAt = ($this->confirmPassword)($actor, Input::string($data['password'], 'password'));
        $provider = Input::string($data['provider'], 'provider');
        ($this->ensureProvider)($provider);
        $code = Str::random(64);
        $expiresAt = now()->addMinutes(5);
        $intent = new NativeLinkIntent(
            $actor->id,
            $provider,
            Input::string($data['challenge'], 'challenge'),
            $confirmedAt,
            $expiresAt->getTimestamp(),
            $actor->securityFingerprint(),
        );
        Cache::put('native-link:' . hash('sha256', $code), $intent->toArray(), $expiresAt);

        return $code;
    }
}
