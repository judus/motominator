<?php

namespace App\Accounts\Actions\Fortify;

use App\Accounts\Actions\ConfirmAccountPassword;
use App\Models\User;
use App\Support\Input;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(private readonly ConfirmAccountPassword $confirmPassword)
    {
    }

    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $emailChanged = DB::transaction(function () use ($user, $input): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $previousEmail = $locked->email;
            $this->updateProfile($locked, $input);

            return $previousEmail !== $locked->email;
        });
        $user->refresh();
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }
    }

    /** @param array<string, mixed> $input */
    private function updateProfile(User $user, array $input): void
    {
        if (Config::boolean('fortify.lowercase_usernames') && is_string($input['email'] ?? null)) {
            $input['email'] = Str::lower($input['email']);
        }
        $validated = Input::object(Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
        ])->validateWithBag('updateProfileInformation'));
        if ($validated['email'] !== $user->email) {
            ($this->confirmPassword)(
                $user,
                Input::string($input['current_password'] ?? '', 'current_password'),
                'current_password',
            );
        }
        $input = [
            'name' => Input::string($validated['name'], 'name'),
            'email' => Input::string($validated['email'], 'email'),
        ];

        if ($input['email'] !== $user->email) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
            ])->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();
    }
}
