<?php

namespace App\Accounts\Actions;

use App\Models\User;
use App\Support\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class AuthenticateDevice
{
    public function __construct(
        private readonly TwoFactorAuthenticationProvider $twoFactor,
        private readonly IssueDeviceToken $issueDeviceToken,
    ) {
    }

    /**
     * A null result means a two-factor challenge is required; no token has been issued.
     *
     * @param array<string, mixed> $input
     * @return array{token: string, expires_at: string}|null
     */
    public function __invoke(array $input): ?array
    {
        $data = Input::object(Validator::make($input, [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:100'],
        ])->validate());

        return DB::transaction(function () use ($data): ?array {
            $user = User::query()
                ->where('email', strtolower(Input::string($data['email'], 'email')))
                ->lockForUpdate()
                ->first();
            if (! $user || ! Hash::check(Input::string($data['password'], 'password'), $user->password ?? '')) {
                throw ValidationException::withMessages(['email' => 'The provided credentials are incorrect.']);
            }
            if ($user->hasEnabledTwoFactorAuthentication()) {
                if (empty($data['code']) && empty($data['recovery_code'])) {
                    return null;
                }
                $this->verifyTwoFactor($user, $data);
            }

            return ($this->issueDeviceToken)($user, Input::string($data['device_name'], 'device_name'));
        });
    }

    /** @param array<string, mixed> $data */
    private function verifyTwoFactor(User $user, array $data): void
    {
        $recovery = $data['recovery_code'] ?? null;
        if (is_string($recovery) && filled($user->two_factor_recovery_codes)) {
            foreach ($user->recoveryCodes() as $storedCode) {
                if (is_string($storedCode) && hash_equals($storedCode, $recovery)) {
                    $user->replaceRecoveryCode($storedCode);

                    return;
                }
            }
        }
        $secret = Input::string(Fortify::currentEncrypter()->decrypt($user->two_factor_secret ?? ''), 'code');
        if (! $this->twoFactor->verify($secret, Input::string($data['code'] ?? '', 'code'))) {
            throw ValidationException::withMessages(['code' => 'The two-factor code is invalid.']);
        }
    }
}
