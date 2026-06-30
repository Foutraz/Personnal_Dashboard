<?php

namespace Functional\Health\Providers;

use Functional\Health\Database\Seeders\HealthSeeder;
use Technical\Osdd\Providers\OsddServiceProvider;

class HealthServiceProvider extends OsddServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'health');

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
            $this->loadSeeders([HealthSeeder::class]);
        }
    }
}
