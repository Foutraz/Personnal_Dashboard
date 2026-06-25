<?php

use Functional\RecurringExpenses\Livewire\ExpensesDashboard;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:web'])->group(function () {
    Route::get('/recurring-expenses', ExpensesDashboard::class)->name('recurring-expenses');
});
