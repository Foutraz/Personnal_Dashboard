<?php

use Functional\Planning\Http\Controllers\CalendarConnectionController;
use Functional\Planning\Livewire\PlanningDashboard;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:web'])->group(function () {
    Route::get('/planning', PlanningDashboard::class)->name('planning');
    Route::get('/planning/{provider}/connect', [CalendarConnectionController::class, 'connect'])->name('planning.connect');
    Route::get('/planning/{provider}/callback', [CalendarConnectionController::class, 'callback'])->name('planning.callback');
    Route::post('/planning/{provider}/sync', [CalendarConnectionController::class, 'syncNow'])->name('planning.sync');
});
