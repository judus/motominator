<?php

namespace App\Accounts\Providers;

use App\Accounts\Console\Commands\GrantAdmin;
use App\Accounts\Policies\UserPolicy;
use App\Accounts\Observers\UserSecurityObserver;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AccountsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        User::observe(UserSecurityObserver::class);
        Gate::policy(User::class, UserPolicy::class);
        $this->commands([GrantAdmin::class]);
    }
}
