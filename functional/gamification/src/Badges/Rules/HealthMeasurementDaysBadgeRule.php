<?php

namespace Functional\Gamification\Badges\Rules;

use Carbon\CarbonInterface;
use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\BadgeUnit;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Health\Models\BodyMeasurement;
use Functional\Users\Models\User;

class HealthMeasurementDaysBadgeRule implements BadgeRule
{
    /**
     * Create the rule with the gamification calendar.
     */
    public function __construct(private GamificationCalendar $calendar) {}

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
    public function unit(): BadgeUnit
    {
        return BadgeUnit::Days;
    }

    /**
     * Count the user's distinct measurement days in the gamification timezone.
     */
    public function measure(User $user): float
    {
        return (float) BodyMeasurement::query()
            ->whereBelongsTo($user)
            ->pluck('measured_at')
            ->map(fn (CarbonInterface $measuredAt): string => $this->calendar->dayOf($measuredAt))
            ->unique()
            ->count();
    }
}
