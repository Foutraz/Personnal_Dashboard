<?php

namespace Functional\Planning\Dashboard;

use Carbon\CarbonPeriod;
use Functional\Planning\Models\CalendarEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Osdd\Contracts\ProvidesAgendaItems;
use Technical\Osdd\Dto\AgendaItem;

final class PlanningAgendaProvider implements ProvidesAgendaItems
{
    /**
     * Map the user's calendar events within the period to agenda items.
     */
    public function agendaItems(Authenticatable $user, CarbonPeriod $period): Collection
    {
        return CalendarEvent::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereBetween('starts_at', [$period->getStartDate(), $period->getEndDate()])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (CalendarEvent $event): AgendaItem => new AgendaItem(
                id: $event->id,
                source: $event->provider === IntegrationProvider::GoogleCalendar ? 'google' : 'outlook',
                title: $event->title,
                startsAt: $event->starts_at,
                endsAt: $event->ends_at,
                allDay: $event->all_day,
                accent: $event->provider === IntegrationProvider::GoogleCalendar ? 'cyan' : 'violet',
                location: $event->location,
                link: $event->external_link,
                href: route('planning'),
            ));
    }
}
