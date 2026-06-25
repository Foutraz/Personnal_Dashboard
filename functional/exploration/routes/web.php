<?php

use Functional\Exploration\Livewire\ExplorationDashboard;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:web'])->group(function () {
    Route::get('/exploration', ExplorationDashboard::class)->name('exploration');
});
