<?php

namespace Technical\Osdd\Contracts;

use Carbon\CarbonPeriod;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Technical\Osdd\Dto\AgendaItem;

interface ProvidesAgendaItems
{
    /**
     * Return this module's agenda items within the period for the given user.
     *
     * @return Collection<int, AgendaItem>
     */
    public function agendaItems(Authenticatable $user, CarbonPeriod $period): Collection;
}
