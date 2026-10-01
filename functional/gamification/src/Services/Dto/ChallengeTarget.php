<?php

namespace Functional\Gamification\Services\Dto;

final readonly class ChallengeTarget
{
    public function __construct(
        public float $baseline,
        public float $target,
    ) {}
}
