<?php

use App\Http\Controllers\AuthController;
use App\Rest\Controllers\SportActivitiesController;
use App\Rest\Controllers\UsersController;
use Lomkit\Rest\Facades\Rest;

Route::middleware('auth:api')->group(function () {
    Rest::resource('users', UsersController::class);
    Rest::resource('sportActivities', SportActivitiesController::class);
    Route::prefix('auth')->group(function () {
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});
