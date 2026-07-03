<?php

use Functional\Gamification\Livewire\PlayerProfilePage;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:web'])->group(function () {
    Route::get('/player', PlayerProfilePage::class)->name('player');
});
