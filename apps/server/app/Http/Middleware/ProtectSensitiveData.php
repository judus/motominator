<?php

namespace App\Http\Middleware;

use Closure;
use Fruitcake\LaravelDebugbar\LaravelDebugbar;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;
use Symfony\Component\HttpFoundation\Response;

class ProtectSensitiveData
{
    public function __construct(private readonly ?LaravelDebugbar $debugbar = null)
    {
    }

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->is('api/v1/status', 'api/v1/auth/config') || ! $request->is(
                'login',
                'logout',
                'register',
                'forgot-password',
                'reset-password',
                'reset-password/*',
                'two-factor-challenge',
                'user/*',
                'email/*',
                'auth/*',
                'api/v1/*',
                'admin',
                'admin/*',
                'livewire/*',
            )
        ) {
            return $next($request);
        }
        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }
        $this->debugbar?->disable();

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
