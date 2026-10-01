<?php

namespace Functional\Gamification\Badges\Rules;

use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\BadgeUnit;
use Functional\Gamification\Models\Streak;
use Functional\Users\Models\User;

abstract class StreakBadgeRule implements BadgeRule
{
    /**
     * Get the unit in which the rule expresses its measure.
     */
    public function unit(): string
    {
        return BadgeUnit::Days->value;
    }

    /**
     * Measure the user's best streak of the rule's domain.
     */
    public function measure(User $user): float
    {
        return (float) Streak::query()
            ->whereBelongsTo($user)
            ->where('domain', $this->domain())
            ->max('best_count');
    }
}
