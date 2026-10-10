<?php

use App\Accounts\Providers\AccountsServiceProvider;
use App\Accounts\Providers\FortifyServiceProvider;
use App\Activity\Providers\ActivityServiceProvider;
use App\Ai\Providers\AiServiceProvider;
use App\Garage\Providers\GarageServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AccountsServiceProvider::class,
    GarageServiceProvider::class,
    ActivityServiceProvider::class,
    AiServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
];
