<?php

namespace Functional\Gamification\Services\Dto;

use Functional\Gamification\Enums\GamificationDomain;
use Illuminate\Support\Carbon;

final readonly class XpAward
{
    public function __construct(
        public GamificationDomain $domain,
        public string $ruleKey,
        public string $sourceType,
        public string $sourceId,
        public int $points,
        public Carbon $occurredAt,
    ) {}
}
