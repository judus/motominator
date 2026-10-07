<?php

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;

class Register extends BaseRegister
{
    public function mount(): void
    {
        abort_unless(config('auth.registration_enabled'), 403);

        parent::mount();
    }

    public function register(): ?RegistrationResponse
    {
        abort_unless(config('auth.registration_enabled'), 403);

        $response = parent::register();

        if ($response !== null) {
            Filament::auth()->logout();
            session()->invalidate();
            session()->regenerateToken();

            Notification::make()
                ->title('Account created')
                ->body('Your account is ready. An administrator must grant access before you can sign in to the admin panel.')
                ->success()
                ->send();
        }

        return $response;
    }

    protected function sendEmailVerificationNotification(Model $user): void
    {
        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }
    }
}
