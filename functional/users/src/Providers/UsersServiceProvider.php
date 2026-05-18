<?php

namespace Functional\Users\Providers;

use Functional\Users\Database\Seeders\UsersSeeder;
use Xefi\LaravelOSDD\LayerServiceProvider;

class UsersServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
            $this->loadSeeders([UsersSeeder::class]);
            $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        }
    }

    public function register(): void
    {
        //
    }
}
