<?php

namespace Technical\Application\Providers;

use Technical\Osdd\Providers\OsddServiceProvider;

class ApplicationServiceProvider extends OsddServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }
    }

    public function register(): void
    {
        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/app.php', 'app');
        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/auth.php', 'auth');
        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/cors.php', 'cors');
        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/database.php', 'database');
        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/filesystems.php', 'filesystems');
        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/queue.php', 'queue');
        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/services.php', 'services');
        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/session.php', 'session');
    }
}
