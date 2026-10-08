<?php

use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\NativeSocialAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/v1/auth/native', [NativeSocialAuthController::class, 'store'])->middleware('throttle:10,1');
Route::post('/v1/auth/native/exchange', [NativeSocialAuthController::class, 'exchange'])->middleware('throttle:20,1');
Route::post('/v1/auth/tokens', [DeviceTokenController::class, 'store'])->middleware('throttle:mobile-login');
Route::delete('/v1/auth/token', [DeviceTokenController::class, 'destroy'])->middleware('auth:sanctum');

Route::get('/v1/status', fn (): array => [
    'name' => 'Motominator',
    'status' => 'ok',
])->name('api.v1.status');

Route::get('/v1/auth/config', fn (): array => [
    'registration_enabled' => (bool) config('auth.registration_enabled'),
    'providers' => collect(['google', 'github'])->filter(fn (string $provider): bool => filled(config("services.{$provider}.client_id")) && filled(config("services.{$provider}.client_secret")))->values()->all(),
]);

Route::get('/v1/user', function (Request $request): array {
    $user = $request->user();

    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'email_verified_at' => $user->email_verified_at,
        'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
        'two_factor_pending' => filled($user->two_factor_secret) && ! $user->hasEnabledTwoFactorAuthentication(),
        'providers' => $user->socialIdentities()->pluck('provider')->all(),
    ];
})->middleware(['auth:sanctum', 'abilities:account:read'])->name('api.v1.user');
