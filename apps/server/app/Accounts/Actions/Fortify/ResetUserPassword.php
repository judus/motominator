<?php

namespace App\Accounts\Actions\Fortify;

use App\Models\User;
use App\Support\Input;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly PasswordBroker $passwordBroker)
    {
    }

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Input::object(Validator::make($input, [
            'password' => $this->passwordRules(),
            'token' => ['required', 'string'],
        ])->validate());

        DB::transaction(function () use ($user, $input): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $this->passwordBroker->tokenExists($locked, $input['token'])) {
                throw ValidationException::withMessages(['email' => 'This password reset link is invalid or expired.']);
            }
            $locked->forceFill(['password' => Hash::make($input['password'])])->save();
        });
        $user->refresh();
    }
}
