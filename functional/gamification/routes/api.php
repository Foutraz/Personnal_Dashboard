<?php

use Functional\Gamification\Rest\Controller\PlayerProfilesController;
use Functional\Gamification\Rest\Controller\XpEntriesController;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;

Route::prefix('api')->group(function () {
    Route::middleware('auth:api')->group(function () {
        Rest::resource('xp-entries', XpEntriesController::class);
        Rest::resource('player-profiles', PlayerProfilesController::class);
    });
});
