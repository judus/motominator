<?php

use App\Accounts\Http\Controllers\AccountController;
use App\Accounts\Http\Controllers\AccountSettingsController;
use App\Accounts\Http\Controllers\DeviceTokenController;
use App\Accounts\Http\Controllers\NativeSocialAuthController;
use App\Accounts\Http\Controllers\NativeSocialLinkController;
use App\Ai\Http\Controllers\AiSettingsController;
use App\Garage\Http\Controllers\InvoiceImportController;
use App\Garage\Http\Controllers\MaintenanceRecordController;
use App\Garage\Http\Controllers\MotorcycleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Ai\Http\Controllers\CopilotController;

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
    'providers' => collect(['google', 'github'])->filter(
        fn (string $provider): bool => filled(
            config("services.{$provider}.client_id")
        ) && filled(
            config("services.{$provider}.client_secret")
        )
    )->values()->all(),
]);

Route::get('/v1/user', [AccountController::class, 'show'])->middleware(
    ['auth:sanctum', 'abilities:account:read']
)->name(
    'api.v1.user'
);

Route::prefix('v1')->name('api.v1.')->middleware('auth:sanctum')->group(function (): void {
    Route::prefix('account')->name('account.')->group(function (): void {
        Route::get('devices', [AccountSettingsController::class, 'devices'])->middleware('abilities:account:read');
        Route::middleware(['abilities:account:write', 'throttle:10,1'])->group(function (): void {
            Route::put('profile', [AccountSettingsController::class, 'profile']);
            Route::put('password', [AccountSettingsController::class, 'password']);
            Route::post('two-factor', [AccountSettingsController::class, 'twoFactor']);
            Route::delete('devices/{token}', [AccountSettingsController::class, 'revokeDevice']);
            Route::post('social/link', [NativeSocialLinkController::class, 'store']);
            Route::post('social/link/complete', [NativeSocialLinkController::class, 'complete']);
            Route::delete('social/{provider}', [AccountSettingsController::class, 'unlinkSocial']);
        });
        Route::post('verification', [AccountSettingsController::class, 'verification'])
            ->middleware(['abilities:account:write', 'throttle:3,1']);
    });
    Route::prefix('motorcycles/{motorcycle}/invoice-imports')->name('invoice-imports.')->group(function (): void {
        Route::get('/', [InvoiceImportController::class, 'index'])->middleware('abilities:garage:read')->name('index');
        Route::get('/{invoiceImport}', [InvoiceImportController::class, 'show'])->middleware(
            'abilities:garage:read'
        )->name(
            'show'
        );
        Route::get('/{invoiceImport}/download', [InvoiceImportController::class, 'download'])->middleware(
            'abilities:garage:read'
        )->name(
            'download'
        );
        Route::post('/', [InvoiceImportController::class, 'store'])->middleware(
            ['verified', 'abilities:garage:write', 'throttle:20,1']
        )->name(
            'store'
        );
        Route::post('/{invoiceImport}/extract', [InvoiceImportController::class, 'extract'])->middleware(
            ['verified', 'abilities:garage:write,ai:write', 'throttle:3,1']
        )->name(
            'extract'
        );
        Route::put('/{invoiceImport}', [InvoiceImportController::class, 'update'])->middleware(
            ['verified', 'abilities:garage:write']
        )->name(
            'update'
        );
        Route::post('/{invoiceImport}/confirm', [InvoiceImportController::class, 'confirm'])->middleware(
            ['verified', 'abilities:garage:write']
        )->name(
            'confirm'
        );
    });
    Route::prefix('ai/conversations')->name('ai.conversations.')->group(function (): void {
        Route::get('/', [CopilotController::class, 'index'])->middleware('abilities:ai:read')->name('index');
        Route::get('/{conversation}/messages', [CopilotController::class, 'messages'])
            ->middleware('abilities:ai:read')->whereUuid('conversation')->name('messages');
        Route::middleware(['verified', 'abilities:ai:write', 'throttle:10,1'])->group(function (): void {
            Route::post('/', [CopilotController::class, 'store'])->name('store');
            Route::post('/{conversation}/messages', [CopilotController::class, 'reply'])
                ->middleware('abilities:garage:read')->whereUuid('conversation')->name('reply');
        });
        Route::delete('/{conversation}', [CopilotController::class, 'destroy'])
            ->middleware('abilities:ai:write')->whereUuid('conversation')->name('destroy');
    });
    Route::get('ai/settings', [AiSettingsController::class, 'show'])->middleware('abilities:ai:read')->name(
        'ai.settings.show'
    );
    Route::put('ai/settings', [AiSettingsController::class, 'update'])->middleware(
        ['verified', 'abilities:ai:write', 'throttle:20,1']
    )->name(
        'ai.settings.update'
    );
    Route::delete('ai/settings', [AiSettingsController::class, 'destroy'])->middleware('abilities:ai:write')->name(
        'ai.settings.destroy'
    );
    Route::post('ai/settings/test', [AiSettingsController::class, 'test'])->middleware(
        ['verified', 'abilities:ai:write', 'throttle:3,1']
    )->name(
        'ai.settings.test'
    );
    Route::apiResource('motorcycles', MotorcycleController::class)->only(['index', 'show'])->middleware(
        'abilities:garage:read'
    );
    Route::apiResource('motorcycles', MotorcycleController::class)->only(['store', 'update'])->middleware(
        ['verified', 'abilities:garage:write']
    );
    Route::apiResource('motorcycles.maintenance-records', MaintenanceRecordController::class)->only(
        ['index', 'show']
    )->parameters(
        ['maintenance-records' => 'maintenanceRecord']
    )->scoped()->middleware(
        'abilities:garage:read'
    );
    Route::apiResource('motorcycles.maintenance-records', MaintenanceRecordController::class)->only(
        ['store', 'update']
    )->parameters(
        ['maintenance-records' => 'maintenanceRecord']
    )->scoped()->middleware(
        ['verified', 'abilities:garage:write']
    );
});
