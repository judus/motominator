<?php

use App\Accounts\Exceptions\AccountsNativeAuthException;
use App\Accounts\Exceptions\AccountsSocialException;
use App\Accounts\Http\Middleware\ThrottleRecoveryRequests;
use App\Garage\Exceptions\GarageInvoiceException;
use App\Garage\Exceptions\GarageMaintenanceException;
use App\Http\Middleware\AddLogContext;
use App\Http\Middleware\ProtectSensitiveData;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([AddLogContext::class, ProtectSensitiveData::class]);
        $middleware->statefulApi();
        $middleware->alias(['abilities' => CheckAbilities::class]);
        $middleware->web(append: [ThrottleRecoveryRequests::class, AuthenticateSession::class]);
        $middleware->redirectGuestsTo(fn (): string => route('filament.admin.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (
            AccountsNativeAuthException|AccountsSocialException $exception,
            Request $request,
        ) {
            $status = match ($exception->reason) {
                'unsupported_provider' => 404,
                'provider_unavailable' => 503,
                'expired_intent' => 410,
                'invalid_exchange' => 422,
                'identity_already_linked', 'identity_conflict', 'final_sign_in_method' => 409,
            };

            return $request->is('api/*') || $request->expectsJson()
                ? response()->json(['message' => $exception->getMessage(), 'reason' => $exception->reason], $status)
                : response($exception->getMessage(), $status);
        });
        $exceptions->render(function (
            GarageInvoiceException|GarageMaintenanceException $exception,
            Request $request,
        ) {
            return $request->is('api/*') || $request->expectsJson()
                ? response()->json(['message' => $exception->getMessage(), 'reason' => $exception->reason], 409)
                : response($exception->getMessage(), 409);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
