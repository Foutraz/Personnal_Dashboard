<?php

namespace Functional\Planning\Listeners;

use Functional\Planning\Models\CalendarEvent;
use Functional\Users\Events\UserDeleting;

class DeleteUserCalendarEvents
{
    /**
     * Delete the calendar events owned by the deleting user.
     */
    public function handle(UserDeleting $event): void
    {
        CalendarEvent::query()
            ->where('user_id', $event->user->id)
            ->cursor()
            ->each(fn (CalendarEvent $calendarEvent) => $calendarEvent->delete());
    }
}
