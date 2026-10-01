<?php

namespace Functional\Gamification\Badges\Rules;

use Functional\Gamification\Enums\GamificationDomain;

class SportStreakBadgeRule extends StreakBadgeRule
{
    /**
     * Get the badge family key matching the configured thresholds.
     */
    public function key(): string
    {
        return 'sport_streak';
    }

    /**
     * Get the domain the badge family belongs to.
     */
    public function domain(): GamificationDomain
    {
        return GamificationDomain::Sport;
    }
}
