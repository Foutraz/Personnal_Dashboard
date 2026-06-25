<?php

use Functional\Exploration\Rest\Controller\ExploredCellsController;
use Functional\Exploration\Rest\Controller\TripRoutesController;
use Lomkit\Rest\Facades\Rest;

Route::prefix('api')->group(function () {
    Route::middleware('auth:api')->group(function () {
        Rest::resource('explored-cells', ExploredCellsController::class);
        Rest::resource('trip-routes', TripRoutesController::class);
    });
});
