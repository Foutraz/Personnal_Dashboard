<?php

use Functional\RecurringExpenses\Rest\Controller\RecurringExpensesController;
use Lomkit\Rest\Facades\Rest;

Route::prefix('api')->group(function () {
    Route::middleware('auth:api')->group(function () {
        Rest::resource('recurring-expenses', RecurringExpensesController::class)->withSoftDeletes();
    });
});
