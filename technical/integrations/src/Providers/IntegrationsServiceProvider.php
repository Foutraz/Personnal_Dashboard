<?php

namespace Technical\Integrations\Providers;

use Functional\Users\Events\UserDeleting;
use Livewire\Livewire;
use Technical\Integrations\Listeners\DeleteUserIntegrationConnections;
use Technical\Integrations\Livewire\IntegrationsManager;
use Technical\Osdd\Providers\OsddServiceProvider;

class IntegrationsServiceProvider extends OsddServiceProvider
{
    /**
     * The event listener mappings for the layer.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected array $listen = [
        UserDeleting::class => [
            DeleteUserIntegrationConnections::class,
        ],
    ];

    public function boot(): void
    {
        $this->loadListenEvent();
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'integrations');

        Livewire::component('integrations-manager', IntegrationsManager::class);

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }
    }

    public function register(): void
    {
        //
    }
}
