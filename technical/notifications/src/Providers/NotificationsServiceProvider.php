<?php

namespace Technical\Notifications\Providers;

use Livewire\Livewire;
use Technical\Notifications\Livewire\NotificationCenter;
use Technical\Osdd\Providers\OsddServiceProvider;

class NotificationsServiceProvider extends OsddServiceProvider
{
    /**
     * Bootstrap the notification center views and Livewire component.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'notifications');

        Livewire::component('notification-center', NotificationCenter::class);
    }
}
