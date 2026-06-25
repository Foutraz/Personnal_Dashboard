<?php

use Functional\Todo\Livewire\TodoBoard;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:web'])->group(function () {
    Route::get('/todo', TodoBoard::class)->name('todo');
});
