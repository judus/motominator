<?php

use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\NativeSocialAuthController;
use App\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/mobile/complete', [NativeSocialAuthController::class, 'complete'])->middleware(['auth', 'throttle:20,1'])->name('auth.mobile.complete');

Route::get('/user/devices', [DeviceTokenController::class, 'index'])->middleware('auth');
Route::delete('/user/devices/{token}', [DeviceTokenController::class, 'revoke'])->middleware(['auth', 'password.confirm', 'throttle:10,1']);

Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->middleware('throttle:20,1');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->middleware('throttle:20,1');
Route::delete('/auth/{provider}', [SocialAuthController::class, 'destroy'])->middleware(['auth', 'password.confirm', 'throttle:10,1']);

Route::get('/', function () {
    return view('welcome');
});
