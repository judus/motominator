<?php

use Illuminate\Support\Facades\Route;

Route::get('/v1/status', fn (): array => [
    'name' => 'Motominator',
    'status' => 'ok',
])->name('api.v1.status');
