<?php

use Functional\Planning\Rest\Controller\CalendarEventsController;
use Lomkit\Rest\Facades\Rest;

Route::prefix('api')->group(function () {
    Route::middleware('auth:api')->group(function () {
        Rest::resource('calendar-events', CalendarEventsController::class)->withSoftDeletes();
    });
});
