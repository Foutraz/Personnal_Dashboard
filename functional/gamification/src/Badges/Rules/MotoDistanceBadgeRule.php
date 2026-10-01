<?php

namespace Functional\Gamification\Badges\Rules;

use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\BadgeUnit;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Moto\Models\MotoRide;
use Functional\Users\Models\User;

class MotoDistanceBadgeRule implements BadgeRule
{
    /**
     * Get the badge family key matching the configured thresholds.
     */
    public function key(): BadgeRuleKey
    {
        return BadgeRuleKey::MotoDistance;
    }

    /**
     * Get the domain the badge family belongs to.
     */
    public function domain(): GamificationDomain
    {
        return GamificationDomain::Moto;
    }

    /**
     * Get the unit in which the rule expresses its measure.
     */
    public function unit(): BadgeUnit
    {
        return BadgeUnit::Kilometers;
    }

    /**
     * Measure the user's progress with a single scoped aggregation.
     */
    public function measure(User $user): float
    {
        return (float) MotoRide::query()->whereBelongsTo($user)->sum('distance');
    }
}
