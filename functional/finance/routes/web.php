<?php

use Functional\Finance\Http\Controllers\GoCardlessConnectionController;
use Functional\Finance\Livewire\FinanceDashboard;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:web'])->group(function () {
    Route::get('/finance', FinanceDashboard::class)->name('finance');
    Route::get('/finance/gocardless/connect', [GoCardlessConnectionController::class, 'connect'])->name('finance.gocardless.connect');
    Route::get('/finance/gocardless/callback', [GoCardlessConnectionController::class, 'callback'])->name('finance.gocardless.callback');
    Route::post('/finance/gocardless/sync', [GoCardlessConnectionController::class, 'syncNow'])->name('finance.gocardless.sync');
});
