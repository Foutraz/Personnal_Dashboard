<?php

use Functional\Goals\Rest\Controller\GoalsController;
use Lomkit\Rest\Facades\Rest;

Route::prefix('api')->group(function () {
    Route::middleware('auth:api')->group(function () {
        Rest::resource('goals', GoalsController::class)->withSoftDeletes();
    });
});
