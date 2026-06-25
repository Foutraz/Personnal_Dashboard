<?php

use Functional\Finance\Livewire\FinanceDashboard;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:web'])->group(function () {
    Route::get('/finance', FinanceDashboard::class)->name('finance');
});
