<?php

namespace Functional\Gamification\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class GamificationCalendar
{
    public function timezone(): string
    {
        return (string) config('gamification.timezone');
    }

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone())->startOfDay();
    }

    public function dayOf(CarbonInterface $moment): string
    {
        return $moment->copy()->setTimezone($this->timezone())->toDateString();
    }

    public function isStreakAlive(?CarbonInterface $lastActivityDay): bool
    {
        return $lastActivityDay !== null && $lastActivityDay->toDateString() >= $this->today()->subDay()->toDateString();
    }
}
