<?php

namespace Functional\Gamification\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class GamificationCalendar
{
    /**
     * Get the timezone in which gamification days are bucketed.
     */
    public function timezone(): string
    {
        return (string) config('gamification.timezone');
    }

    /**
     * Get the start of the current day in the gamification timezone.
     */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone())->startOfDay();
    }

    /**
     * Get the gamification-timezone date string of the given moment.
     */
    public function dayOf(CarbonInterface $moment): string
    {
        return $moment->copy()->setTimezone($this->timezone())->toDateString();
    }

    /**
     * Determine whether a streak whose last active day is given is still alive today.
     */
    public function isStreakAlive(?CarbonInterface $lastActivityDay): bool
    {
        return $lastActivityDay !== null && $lastActivityDay->toDateString() >= $this->today()->subDay()->toDateString();
    }
}
