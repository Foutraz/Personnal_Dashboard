<?php

namespace Functional\Gamification\Services\Dto;

final readonly class ChallengeProgress
{
    public function __construct(
        public bool $targetReached,
        public bool $weekEnded,
        public bool $gracePassed,
    ) {}
}
