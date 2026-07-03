<?php

namespace Functional\Gamification\Services\Dto;

final readonly class LevelTransition
{
    public function __construct(
        public int $previousLevel,
        public int $currentLevel,
    ) {}

    /**
     * Determine whether the transition crossed at least one level upward.
     */
    public function leveledUp(): bool
    {
        return $this->currentLevel > $this->previousLevel;
    }
}
