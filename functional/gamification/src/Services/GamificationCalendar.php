<?php

namespace Functional\Gamification\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class GamificationCalendar
{
    /**
     * Get the configured gamification timezone, falling back to the application timezone when it is not a valid identifier.
     */
    public function timezone(): string
    {
        $configured = (string) config('gamification.timezone');

        return in_array($configured, timezone_identifiers_list(), true) ? $configured : (string) config('app.timezone');
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
