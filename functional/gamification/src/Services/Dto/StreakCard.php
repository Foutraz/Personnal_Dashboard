<?php

namespace Functional\Gamification\Services\Dto;

use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\Streak;

final readonly class StreakCard
{
    public function __construct(
        public GamificationDomain $domain,
        public int $currentCount,
        public int $bestCount,
        public bool $isAlive,
    ) {}

    /**
     * Build the card of a streak projection, showing a zero current count once the streak is no longer alive.
     */
    public static function fromStreak(Streak $streak, bool $isAlive): self
    {
        return new self(
            domain: $streak->domain,
            currentCount: $isAlive ? $streak->current_count : 0,
            bestCount: $streak->best_count,
            isAlive: $isAlive,
        );
    }

    public function countClass(): string
    {
        return $this->isAlive ? $this->domain->textClass() : 'text-muted';
    }
}
