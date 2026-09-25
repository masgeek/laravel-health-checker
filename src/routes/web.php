<?php

use Illuminate\Support\Facades\Route;


Route::middleware(config('healthcheck.route.middleware', ['throttle:60']))
    ->get('/' . ltrim((string) config('healthcheck.route.path', 'health'), '/'), \Masgeek\HealthCheck\Http\Controllers\HealthCheckController::class)
    ->name('health.check');
