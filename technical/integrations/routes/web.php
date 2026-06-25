<?php

use Illuminate\Support\Facades\Route;
use Technical\Integrations\Livewire\IntegrationsManager;

Route::middleware(['web', 'auth:web'])
    ->get('/integrations', IntegrationsManager::class)
    ->name('integrations');
