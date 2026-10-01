<?php

namespace Functional\Gamification\Badges\Rules;

use Functional\Exploration\Models\ExploredCell;
use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\BadgeUnit;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Users\Models\User;

class ExplorationCellsBadgeRule implements BadgeRule
{
    /**
     * Get the badge family key matching the configured thresholds.
     */
    public function key(): string
    {
        return 'exploration_cells';
    }

    /**
     * Get the domain the badge family belongs to.
     */
    public function domain(): GamificationDomain
    {
        return GamificationDomain::Exploration;
    }

    /**
     * Get the unit in which the rule expresses its measure.
     */
    public function unit(): string
    {
        return BadgeUnit::Count->value;
    }

    /**
     * Measure the user's progress with a single scoped aggregation.
     */
    public function measure(User $user): float
    {
        return (float) ExploredCell::query()->whereBelongsTo($user)->count();
    }
}
