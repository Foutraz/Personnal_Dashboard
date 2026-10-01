<?php

namespace Functional\Gamification\Badges\Rules;

use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\BadgeUnit;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Health\Models\BodyMeasurement;
use Functional\Users\Models\User;
use Illuminate\Support\Facades\DB;

class HealthMeasurementDaysBadgeRule implements BadgeRule
{
    /**
     * Get the badge family key matching the configured thresholds.
     */
    public function key(): string
    {
        return 'health_measurement_days';
    }

    /**
     * Get the domain the badge family belongs to.
     */
    public function domain(): GamificationDomain
    {
        return GamificationDomain::Health;
    }

    /**
     * Get the unit in which the rule expresses its measure.
     */
    public function unit(): string
    {
        return BadgeUnit::Days->value;
    }

    /**
     * Measure the user's progress with a single scoped aggregation.
     */
    public function measure(User $user): float
    {
        return (float) BodyMeasurement::query()->whereBelongsTo($user)->distinct()->count(DB::raw('DATE(measured_at)'));
    }
}
