<?php

namespace Technical\WebAuthentication\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Technical\Osdd\Providers\OsddServiceProvider;

class WebAuthenticationServiceProvider extends OsddServiceProvider
{
    /**
     * Register any application services.
     *
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $this->mergeConfigWithPriorityFrom(
            __DIR__.'/../../config/services.php', 'services'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'web-authentication');
    }
}
