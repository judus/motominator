<?php

namespace Tests\Feature;

use App\Logging\ConfigureLogging;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Laravel\Pail\File;
use Laravel\Pail\Files;
use Laravel\Pail\Handler;
use Mockery;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Tests\TestCase;

class LoggingProbeJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Log::info('queue.probe', ['trace_id' => Context::get('trace_id'), 'job_id' => Context::get('job_id')]);
    }
}
