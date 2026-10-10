<?php

namespace App\Accounts\Actions;

use App\Models\User;
use App\Support\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Fortify;

class ManageAccountTwoFactor
{
    public function __construct(
        private readonly ConfirmAccountPassword $confirmPassword,
        private readonly EnableTwoFactorAuthentication $enable,
        private readonly DisableTwoFactorAuthentication $disable,
        private readonly ConfirmTwoFactorAuthentication $confirm,
        private readonly GenerateNewRecoveryCodes $regenerate,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{secret: string|null, codes: list<string>}
     */
    public function __invoke(User $actor, array $input): array
    {
        $data = Input::object(Validator::make($input, [
            'operation' => ['required', 'in:enable,disable,confirm,codes,regenerate'],
            'password' => ['required', 'string'],
            'code' => ['required_if:operation,confirm', 'nullable', 'string', 'max:20'],
        ])->validate());

        return DB::transaction(function () use ($actor, $data): array {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            ($this->confirmPassword)($user, Input::string($data['password'], 'password'));
            $operation = Input::string($data['operation'], 'operation');
            if ($operation === 'enable') {
                ($this->enable)($user);
            } elseif ($operation === 'disable') {
                ($this->disable)($user);
            } else {
                if ($user->two_factor_secret === null) {
                    throw ValidationException::withMessages(['operation' => 'Set up an authenticator first.']);
                }
                if ($operation === 'confirm') {
                    ($this->confirm)($user, Input::string($data['code'], 'code'));
                } elseif ($operation === 'regenerate') {
                    ($this->regenerate)($user);
                }
            }
            $secret = $operation === 'enable'
                ? Input::string(Fortify::currentEncrypter()->decrypt($user->two_factor_secret ?? ''), 'secret')
                : null;
            $codes = [];
            if (in_array($operation, ['enable', 'codes', 'regenerate'], true)) {
                foreach ($user->recoveryCodes() as $code) {
                    $codes[] = Input::string($code, 'recovery_codes');
                }
            }

            return ['secret' => $secret, 'codes' => $codes];
        });
    }
}
