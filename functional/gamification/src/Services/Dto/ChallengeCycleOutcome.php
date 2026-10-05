<?php

namespace Functional\Gamification\Services\Dto;

use Functional\Gamification\Models\Challenge;
use Illuminate\Support\Collection;

final readonly class ChallengeCycleOutcome
{
    /**
     * @param  Collection<int, Challenge>  $proposed
     * @param  Collection<int, Challenge>  $completed
     */
    public function __construct(
        public GamificationWeek $week,
        public Collection $proposed,
        public Collection $completed,
    ) {}
}
