<?php

namespace Technical\WebAuthentication\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Technical\Osdd\Providers\OsddServiceProvider;
use Technical\WebAuthentication\Livewire\Dashboard;
use Technical\WebAuthentication\Services\NavigationItemCollector;

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

        Livewire::component('dashboard', Dashboard::class);

        View::composer('components.ui.sidebar', function (\Illuminate\View\View $view): void {
            $view->with('moduleNavItems', $this->app->make(NavigationItemCollector::class)->all());
        });
    }
}
