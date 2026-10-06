<?php

namespace Technical\Notifications\Providers;

use Functional\Users\Events\UserDeleting;
use Livewire\Livewire;
use Technical\Notifications\Listeners\DeleteUserNotifications;
use Technical\Notifications\Livewire\NotificationCenter;
use Technical\Osdd\Providers\OsddServiceProvider;

class NotificationsServiceProvider extends OsddServiceProvider
{
    /**
     * The event listener mappings for the layer.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected array $listen = [
        UserDeleting::class => [
            DeleteUserNotifications::class,
        ],
    ];

    /**
     * Bootstrap the notification center views and Livewire component.
     */
    public function boot(): void
    {
        $this->loadListenEvent();
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'notifications');

        Livewire::component('notification-center', NotificationCenter::class);
    }
}
