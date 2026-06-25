<?php

use Illuminate\Support\Facades\Route;
use Technical\WebAuthentication\Http\Controllers\LoginController;
use Technical\WebAuthentication\Http\Controllers\RegisterController;
use Technical\WebAuthentication\Http\Controllers\SocialiteController;

Route::middleware('web')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login')->middleware('guest:web');
    Route::post('/login', [LoginController::class, 'login'])->middleware('guest:web');
    Route::get('/register', [RegisterController::class, 'show'])->name('register')->middleware('guest:web');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('guest:web');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth:web');
    Route::get('/auth/google/redirect', [SocialiteController::class, 'redirect'])->name('auth.google.redirect')->middleware('guest:web');
    Route::get('/auth/google/callback', [SocialiteController::class, 'callback'])->name('auth.google.callback')->middleware('guest:web');
});
