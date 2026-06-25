<?php

use Functional\Sport\Rest\Controller\SportActivitiesController;
use Lomkit\Rest\Facades\Rest;

Route::prefix('api')->group(function () {
    Route::middleware('auth:api')->group(function () {
        Rest::resource('sport-activities', SportActivitiesController::class)->withSoftDeletes();
    });
});
