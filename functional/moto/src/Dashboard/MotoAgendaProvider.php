<?php

namespace Functional\Moto\Dashboard;

use Carbon\CarbonPeriod;
use Functional\Moto\Services\FavorableSlotFinder;
use Functional\Moto\Services\WeatherForecastService;
use Functional\Moto\ValueObjects\FavorableSlot;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Technical\Osdd\Contracts\ProvidesAgendaItems;
use Technical\Osdd\Dto\AgendaItem;

final class MotoAgendaProvider implements ProvidesAgendaItems
{
    public function __construct(
        private WeatherForecastService $weather,
        private FavorableSlotFinder $slotFinder,
    ) {}

    /**
     * Map favorable riding weather slots at the configured location within the period to agenda items.
     */
    public function agendaItems(Authenticatable $user, CarbonPeriod $period): Collection
    {
        if (! $this->weather->isConfigured()) {
            return collect();
        }

        $forecast = $this->weather->forecast((float) config('moto.location.lat'), (float) config('moto.location.lon'));

        return collect($this->slotFinder->find($forecast))
            ->map(fn (FavorableSlot $slot): AgendaItem => new AgendaItem(
                id: 'moto-'.$slot->startsAt->getTimestamp(),
                source: 'moto',
                title: 'Créneau moto favorable',
                startsAt: Carbon::instance($slot->startsAt),
                endsAt: Carbon::instance($slot->endsAt),
                allDay: false,
                accent: $slot->rating->accent(),
                href: route('moto'),
            ))
            ->filter(fn (AgendaItem $item): bool => $item->startsAt->betweenIncluded($period->getStartDate(), $period->getEndDate()))
            ->values();
    }
}
