<?php

namespace Technical\WebAuthentication\Services;

use Carbon\CarbonPeriod;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Technical\Osdd\Contracts\ProvidesAgendaItems;
use Technical\Osdd\Dto\AgendaItem;

final class AgendaCollector
{
    /**
     * Collect every tagged module's agenda items over the period, sorted chronologically.
     *
     * @return Collection<int, AgendaItem>
     */
    public function for(Authenticatable $user, CarbonPeriod $period): Collection
    {
        /** @var iterable<int, ProvidesAgendaItems> $providers */
        $providers = app()->tagged('dashboard.agenda');

        return collect($providers)
            ->flatMap(fn (ProvidesAgendaItems $provider): Collection => $provider->agendaItems($user, $period))
            ->sortBy(fn (AgendaItem $item): int => $item->startsAt->getTimestamp())
            ->values();
    }
}
