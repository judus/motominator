<?php

use App\Models\UserActivity;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Telescope\TelescopeServiceProvider;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('model:prune', ['--model' => [UserActivity::class]])->daily();

if (config('app.env') === 'local' && class_exists(TelescopeServiceProvider::class)) {
    Schedule::command('telescope:prune')->daily();
}
