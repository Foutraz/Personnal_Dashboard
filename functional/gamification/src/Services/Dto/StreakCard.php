<?php

namespace Functional\Gamification\Services\Dto;

use Functional\Gamification\Enums\GamificationDomain;

final readonly class StreakCard
{
    public function __construct(
        public GamificationDomain $domain,
        public int $currentCount,
        public int $bestCount,
        public bool $isAlive,
    ) {}

    public function countClass(): string
    {
        return $this->isAlive ? $this->domain->textClass() : 'text-muted';
    }
}
