<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', [\Masgeek\HealthCheck\Http\Controllers\HealthCheckController::class, 'check']);
