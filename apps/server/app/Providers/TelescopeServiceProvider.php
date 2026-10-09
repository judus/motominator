<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters(
            [
                'api_key',
                'password',
                'password_confirmation',
                'current_password',
                'token',
                'code',
                'recovery_code',
                'two_factor_secret',
                'two_factor_recovery_codes',
                '_token',
            ]
        );
        Telescope::hideRequestHeaders([
            'authorization',
            'cookie',
            'set-cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
        Telescope::hideResponseParameters(['token', 'secretKey', 'recovery_codes']);
    }

    protected function authorization(): void
    {
        $this->gate();
        Telescope::auth(fn (Request $request): bool => Gate::forUser($request->user())->allows('viewTelescope'));
    }

    protected function gate(): void
    {
        Gate::define(
            'viewTelescope',
            fn (?User $user = null): bool => $user !== null && $user->is_admin && $user->hasVerifiedEmail()
        );
    }
}
