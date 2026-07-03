<?php

namespace Functional\Gamification\Contracts;

use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Services\Dto\XpAward;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface XpRule
{
    /**
     * Get the unique ledger key identifying the rule.
     */
    public function key(): string;

    /**
     * Get the domain the rule awards experience for.
     */
    public function domain(): GamificationDomain;

    /**
     * Compute the idempotent awards earned by the user since the given date.
     *
     * @return Collection<int, XpAward>
     */
    public function awards(User $user, ?Carbon $since): Collection;
}
