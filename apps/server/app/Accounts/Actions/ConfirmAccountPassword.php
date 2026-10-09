<?php

namespace App\Accounts\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ConfirmAccountPassword
{
    public function __invoke(User $actor, #[\SensitiveParameter] string $password, string $field = 'password'): int
    {
        if ($password === '' || ! Hash::check($password, $actor->password ?? '')) {
            throw ValidationException::withMessages([$field => 'The provided password is incorrect.']);
        }

        return now()->getTimestamp();
    }
}
