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
     * Measure the user's progress, aggregating in the database when possible and loading rows where the rule needs timezone day buckets or the finance capital calculator.
     */
    public function measure(User $user): float;
}
