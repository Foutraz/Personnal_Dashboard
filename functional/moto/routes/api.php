<?php

use Functional\Moto\Rest\Controller\MotoRidesController;
use Lomkit\Rest\Facades\Rest;

Route::prefix('api')->group(function () {
    Route::middleware('auth:api')->group(function () {
        Rest::resource('moto-rides', MotoRidesController::class)->withSoftDeletes();
    });
});
