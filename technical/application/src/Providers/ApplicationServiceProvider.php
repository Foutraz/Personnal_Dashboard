<?php

namespace Technical\Application\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Technical\Application\RateLimiting\AuthenticationAttemptsLimiter;
use Technical\Application\Time\DisplayTimezone;
use Technical\Osdd\Providers\OsddServiceProvider;

class ApplicationServiceProvider extends OsddServiceProvider
{
    public function boot(): void
    {
        Carbon::macro('inDisplayTimezone', fn (): CarbonImmutable => app(DisplayTimezone::class)->toDisplayTime($this));

        RateLimiter::for(AuthenticationAttemptsLimiter::NAME, (new AuthenticationAttemptsLimiter)(...));

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }
    }

    public function register(): void
    {
        $this->app->singleton(DisplayTimezone::class);

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
