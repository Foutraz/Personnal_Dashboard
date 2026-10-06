<?php

use Illuminate\Support\Facades\Route;
use Technical\Application\RateLimiting\AuthenticationAttemptsLimiter;
use Technical\Authentication\Http\Controllers\AuthenticationController;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthenticationController::class, 'login'])->middleware(AuthenticationAttemptsLimiter::middleware());
    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthenticationController::class, 'logout']);
        Route::post('refresh', [AuthenticationController::class, 'refresh']);
        Route::get('me', [AuthenticationController::class, 'me']);
    });
});
