<?php

namespace Functional\Planning\Services;

use Carbon\CarbonPeriod;
use Functional\Planning\Services\Dto\CalendarItem;
use Functional\Planning\Services\Dto\CalendarItemSource;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Technical\Osdd\Contracts\ProvidesAgendaItems;
use Technical\Osdd\Dto\AgendaItem;

class AggregatedCalendarQuery
{
    /**
     * Merge the user's calendar commitments (events, tasks, expenses) within the range.
     *
     * @return Collection<int, CalendarItem>
     */
    public function forUser(Authenticatable $user, Carbon $from, Carbon $to): Collection
    {
        /** @var iterable<int, ProvidesAgendaItems> $providers */
        $providers = app()->tagged('dashboard.agenda');

        return collect($providers)
            ->flatMap(fn (ProvidesAgendaItems $provider): Collection => $provider->agendaItems($user, CarbonPeriod::create($from, $to)))
            ->reject(fn (AgendaItem $item): bool => $item->source === 'moto')
            ->map(fn (AgendaItem $item): CalendarItem => new CalendarItem(
                $item->id,
                CalendarItemSource::from($item->source),
                $item->title,
                $item->startsAt,
                $item->endsAt,
                $item->allDay,
                $item->location,
                $item->link,
                $item->amount,
            ))
            ->sortBy(fn (CalendarItem $item): int => $item->startsAt->getTimestamp())
            ->values();
    }
}
