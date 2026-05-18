<?php

namespace Functional\Users\Providers;

use Functional\Users\Database\Seeders\UsersSeeder;
use Technical\Osdd\Providers\OsddServiceProvider;

class UsersServiceProvider extends OsddServiceProvider
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
