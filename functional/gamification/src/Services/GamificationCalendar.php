<?php

namespace Functional\Gamification\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class GamificationCalendar
{
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfDay();
    }

    public function isStreakAlive(?CarbonInterface $lastActivityDay): bool
    {
        return $lastActivityDay !== null && $lastActivityDay->toDateString() >= $this->today()->subDay()->toDateString();
    }
}
