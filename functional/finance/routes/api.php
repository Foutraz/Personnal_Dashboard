<?php

use Functional\Finance\Rest\Controller\InvestmentTransactionsController;
use Functional\Finance\Rest\Controller\PositionsController;
use Lomkit\Rest\Facades\Rest;

Route::prefix('api')->group(function () {
    Route::middleware('auth:api')->group(function () {
        Rest::resource('positions', PositionsController::class)->withSoftDeletes();
        Rest::resource('investment-transactions', InvestmentTransactionsController::class)->withSoftDeletes();
    });
});
