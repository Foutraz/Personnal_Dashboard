<?php

namespace Functional\Planning\Actions;

use Foutraz\GoogleCalendar\Dto\CalendarEvent as GoogleEvent;
use Foutraz\Outlook\Dto\CalendarEvent as OutlookEvent;
use Functional\Planning\Models\CalendarEvent;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;

class UpsertCalendarEvent
{
    /**
     * Idempotently persist a Google Calendar event for the given connection.
     */
    public function fromGoogle(IntegrationConnection $connection, GoogleEvent $event): CalendarEvent
    {
        return $this->persist($connection, IntegrationProvider::GoogleCalendar, [
            'external_id' => $event->id,
            'title' => $event->summary ?? '(Sans titre)',
            'description' => $event->description,
            'location' => $event->location,
            'starts_at' => $event->start,
            'ends_at' => $event->end,
            'all_day' => $event->allDay,
            'external_link' => $event->htmlLink,
        ]);
    }

    /**
     * Idempotently persist an Outlook event for the given connection.
     */
    public function fromOutlook(IntegrationConnection $connection, OutlookEvent $event): CalendarEvent
    {
        return $this->persist($connection, IntegrationProvider::OutlookCalendar, [
            'external_id' => $event->id,
            'title' => $event->subject ?? '(Sans titre)',
            'description' => $event->bodyPreview,
            'location' => $event->location,
            'starts_at' => $event->start,
            'ends_at' => $event->end,
            'all_day' => $event->allDay,
            'external_link' => $event->webLink,
        ]);
    }

    /**
     * Persist the normalized event attributes keyed on the connection and external identifier.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function persist(IntegrationConnection $connection, IntegrationProvider $provider, array $attributes): CalendarEvent
    {
        return CalendarEvent::query()->updateOrCreate(
            [
                'integration_connection_id' => $connection->id,
                'external_id' => $attributes['external_id'],
            ],
            [
                'user_id' => $connection->user_id,
                'provider' => $provider,
                'title' => $attributes['title'],
                'description' => $attributes['description'],
                'location' => $attributes['location'],
                'starts_at' => $attributes['starts_at'],
                'ends_at' => $attributes['ends_at'],
                'all_day' => $attributes['all_day'],
                'external_link' => $attributes['external_link'],
                'raw' => null,
            ]
        );
    }
}
