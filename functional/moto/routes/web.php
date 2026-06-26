<?php

use Functional\Moto\Livewire\MotoDashboard;

Route::middleware(['web', 'auth:web'])->group(function () {
    Route::get('/moto', MotoDashboard::class)->name('moto');
});
