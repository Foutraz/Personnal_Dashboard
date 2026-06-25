<?php

use Functional\Sport\Http\Controllers\StravaConnectionController;
use Functional\Sport\Livewire\SportDashboard;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:web'])->group(function () {
    Route::get('/sport', SportDashboard::class)->name('sport');
    Route::get('/sport/strava/connect', [StravaConnectionController::class, 'connect'])->name('sport.strava.connect');
    Route::get('/sport/strava/callback', [StravaConnectionController::class, 'callback'])->name('sport.strava.callback');
    Route::post('/sport/strava/sync', [StravaConnectionController::class, 'syncNow'])->name('sport.strava.sync');
});
