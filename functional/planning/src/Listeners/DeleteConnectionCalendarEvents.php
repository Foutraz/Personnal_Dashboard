<?php

namespace Functional\Planning\Listeners;

use Functional\Planning\Models\CalendarEvent;
use Technical\Integrations\Models\IntegrationConnection;

class DeleteConnectionCalendarEvents
{
    /**
     * Delete the events owned by the deleting connection.
     */
    public function handle(IntegrationConnection $connection): void
    {
        CalendarEvent::query()
            ->where('integration_connection_id', $connection->id)
            ->cursor()
            ->each(fn (CalendarEvent $event) => $event->delete());
    }
}
