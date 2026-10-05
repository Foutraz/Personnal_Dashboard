<?php

namespace Technical\Notifications\Listeners;

use Functional\Users\Events\UserDeleting;
use Illuminate\Notifications\DatabaseNotification;

class DeleteUserNotifications
{
    /**
     * Delete every database notification addressed to the deleting user.
     */
    public function handle(UserDeleting $event): void
    {
        $event->user->notifications()
            ->cursor()
            ->each(fn (DatabaseNotification $notification) => $notification->delete());
    }
}
