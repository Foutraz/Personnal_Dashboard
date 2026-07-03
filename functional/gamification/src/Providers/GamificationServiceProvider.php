<?php

namespace Functional\Gamification\Providers;

use Technical\Osdd\Providers\OsddServiceProvider;

class GamificationServiceProvider extends OsddServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();

        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/gamification.php', 'gamification');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }
    }
}
