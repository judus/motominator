<?php

namespace App\Accounts\Observers;

use App\Models\User;
use App\Support\Input;
use Illuminate\Contracts\Auth\PasswordBroker;

class UserSecurityObserver
{
    public function __construct(private readonly PasswordBroker $passwordBroker)
    {
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged('password')) {
            $user->tokens()->delete();
            $this->passwordBroker->deleteToken($user);
        }
        if ($user->wasChanged('email')) {
            $previous = new User();
            $previous->email = Input::string($user->getOriginal('email'), 'email');
            $this->passwordBroker->deleteToken($previous);
        }
    }
}
