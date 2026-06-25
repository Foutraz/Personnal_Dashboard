<?php

use Functional\Goals\Livewire\GoalsDashboard;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:web'])->group(function () {
    Route::get('/goals', GoalsDashboard::class)->name('goals');
});
