<?php

namespace Technical\Integrations\Providers;

use Functional\Users\Events\UserDeleting;
use Technical\Integrations\Listeners\DeleteUserIntegrationConnections;
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

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }
    }

    public function register(): void
    {
        //
    }
}
