<?php

namespace App\Providers;

use App\Http\Middleware\ProtectSensitiveData;
use App\Logging\RedactingPailHandler;
use Fruitcake\LaravelDebugbar\LaravelDebugbar;
use Illuminate\Foundation\Application;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Laravel\Pail\Files;
use Laravel\Pail\Handler;
use Laravel\Telescope\TelescopeServiceProvider as TelescopePackageProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ProtectSensitiveData::class, function (
            Application $app,
        ): ProtectSensitiveData {
            $debugbar = $app->bound('debugbar') ? $app->make('debugbar') : null;

            return new ProtectSensitiveData(
                $debugbar instanceof LaravelDebugbar ? $debugbar : null,
            );
        });
        if (class_exists(Handler::class)) {
            $this->app->singleton(
                Handler::class,
                fn (Application $app) => new RedactingPailHandler(
                    $app,
                    $app->make(Files::class),
                    $app->runningInConsole()
                )
            );
        }

        if (! $this->app->environment('local')) {
            return;
        }

        if (class_exists(TelescopePackageProvider::class)) {
            $this->app->register(TelescopePackageProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        if (class_exists(\Fruitcake\LaravelDebugbar\ServiceProvider::class)) {
            $this->app->register(\Fruitcake\LaravelDebugbar\ServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::before(function (JobProcessing $event): void {
            Context::add('job_id', $event->job->uuid() ?? $event->job->getJobId());
            Log::info(
                'queue.job_started',
                [
                    'connection' => $event->connectionName,
                    'job' => $event->job->resolveName(),
                    'attempt' => $event->job->attempts()
                ]
            );
        });
        Queue::after(function (JobProcessed $event): void {
            Log::info(
                'queue.job_completed',
                ['connection' => $event->connectionName, 'job' => $event->job->resolveName()]
            );
            Context::forget('job_id');
        });
        Queue::failing(function (JobFailed $event): void {
            Log::error(
                'queue.job_failed',
                [
                    'connection' => $event->connectionName,
                    'job' => $event->job->resolveName(),
                    'exception_class' => $event->exception::class
                ]
            );
            Context::forget('job_id');
        });
    }
}
