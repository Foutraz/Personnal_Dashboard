<?php

use Functional\Health\Http\Controllers\WithingsConnectionController;
use Functional\Health\Livewire\HealthDashboard;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:web'])->group(function () {
    Route::get('/health', HealthDashboard::class)->name('health');
    Route::get('/health/withings/connect', [WithingsConnectionController::class, 'connect'])->name('health.withings.connect');
    Route::get('/health/withings/callback', [WithingsConnectionController::class, 'callback'])->name('health.withings.callback');
    Route::post('/health/withings/sync', [WithingsConnectionController::class, 'syncNow'])->name('health.withings.sync');
});
