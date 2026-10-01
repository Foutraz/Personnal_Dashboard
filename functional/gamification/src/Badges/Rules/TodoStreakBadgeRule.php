<?php

namespace Functional\Gamification\Badges\Rules;

use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\GamificationDomain;

class TodoStreakBadgeRule extends StreakBadgeRule
{
    /**
     * Get the badge family key matching the configured thresholds.
     */
    public function key(): BadgeRuleKey
    {
        return BadgeRuleKey::TodoStreak;
    }

    /**
     * Get the domain the badge family belongs to.
     */
    public function domain(): GamificationDomain
    {
        return GamificationDomain::Todo;
    }
}
