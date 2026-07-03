<?php

namespace Technical\Osdd\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\Facades\Event;
use Xefi\LaravelOSDD\LayerServiceProvider;

class OsddServiceProvider extends LayerServiceProvider
{
    /**
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/osdd.php', 'osdd');
        $this->mergeConfigWithPriorityFrom(__DIR__.'/../../config/rest.php', 'rest');
    }

    /**
     * @throws BindingResolutionException
     */
    protected function mergeConfigWithPriorityFrom(string $path, string $configKey): void
    {
        if (! ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached())) {
            $config = $this->app->make('config');

            $config->set($configKey, array_merge(
                $config->get($configKey, []), require $path
            ));
        }
    }

    protected function loadListenEvent(): void
    {
        $listenEvent = $this->listen ?? [];

        foreach ($listenEvent as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
