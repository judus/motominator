<?php

namespace App\Accounts\Actions\Fortify;

use App\Models\User;
use App\Accounts\Actions\ConfirmAccountPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly ConfirmAccountPassword $confirmPassword)
    {
    }

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string'],
            'password' => $this->passwordRules(),
        ])->validateWithBag('updatePassword');

        DB::transaction(function () use ($user, $input): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            ($this->confirmPassword)($locked, $input['current_password'], 'current_password');
            $locked->forceFill(['password' => Hash::make($input['password'])])->save();
        });
        $user->refresh();
    }
}
