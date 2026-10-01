<?php

namespace Functional\Gamification\Contracts;

use Functional\Gamification\Enums\BadgeUnit;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Users\Models\User;

interface BadgeRule
{
    public const TAG = 'gamification.badge_rules';

    /**
     * Get the badge family key matching the configured thresholds.
     */
    public function key(): string;

    /**
     * Get the domain the badge family belongs to.
     */
    public function domain(): GamificationDomain;

    /**
     * Get the unit in which the rule expresses its measure.
     */
    public function unit(): BadgeUnit;

    /**
     * Measure the user's progress with a single scoped aggregation.
     */
    public function measure(User $user): float;
}
