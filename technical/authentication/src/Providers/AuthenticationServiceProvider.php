<?php

namespace Technical\Authentication\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Technical\Osdd\Providers\OsddServiceProvider;

class AuthenticationServiceProvider extends OsddServiceProvider
{
    /**
     * Register any application services.
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $this->mergeConfigWithPriorityFrom(
            __DIR__ . '/../../config/jwt.php', 'jwt'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
    }
}
