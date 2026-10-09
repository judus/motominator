<?php

namespace App\Activity\Providers;

use App\Activity\Policies\UserActivityPolicy;
use App\Models\UserActivity;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ActivityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(UserActivity::class, UserActivityPolicy::class);
    }
}
