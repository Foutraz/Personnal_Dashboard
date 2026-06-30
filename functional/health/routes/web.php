<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:web'])->group(function () {
    Route::get('/health', fn () => view('health::health'))->name('health');
});
