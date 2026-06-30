<?php

namespace Tests\Feature\Dashboard\Fixtures;

use Carbon\CarbonPeriod;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Technical\Osdd\Contracts\ProvidesAgendaItems;
use Technical\Osdd\Dto\AgendaItem;

class FakeAgendaProvider implements ProvidesAgendaItems
{
    public function __construct(private string $id, private Carbon $startsAt) {}

    /**
     * Return one deterministic agenda item to prove tag aggregation and ordering.
     */
    public function agendaItems(Authenticatable $user, CarbonPeriod $period): Collection
    {
        return collect([new AgendaItem(
            id: $this->id,
            source: 'task',
            title: 'Fake '.$this->id,
            startsAt: $this->startsAt,
            endsAt: null,
            allDay: false,
            accent: 'lime',
        )]);
    }
}
