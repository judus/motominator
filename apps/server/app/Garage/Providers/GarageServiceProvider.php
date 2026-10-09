<?php

namespace App\Garage\Providers;

use App\Garage\Policies\MaintenanceRecordPolicy;
use App\Garage\Policies\MotorcyclePolicy;
use App\Models\MaintenanceRecord;
use App\Models\Motorcycle;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class GarageServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Motorcycle::class, MotorcyclePolicy::class);
        Gate::policy(MaintenanceRecord::class, MaintenanceRecordPolicy::class);
    }
}
