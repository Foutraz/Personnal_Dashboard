<?php

use Functional\Todo\Rest\Controller\TasksController;
use Lomkit\Rest\Facades\Rest;

Route::prefix('api')->group(function () {
    Route::middleware('auth:api')->group(function () {
        Rest::resource('tasks', TasksController::class)->withSoftDeletes();
    });
});
