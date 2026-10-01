<?php

namespace Functional\Gamification\Exceptions;

use Functional\Gamification\Enums\BadgeRuleKey;
use RuntimeException;

class NonIncreasingBadgeThresholdsException extends RuntimeException
{
    /**
     * @param  array<string, int|float|string>  $thresholds
     */
    public function __construct(public readonly BadgeRuleKey $ruleKey, public readonly array $thresholds)
    {
        $configured = collect($thresholds)->map(fn (int|float|string $threshold, string $tier): string => "{$tier}={$threshold}")->implode(', ');

        parent::__construct("The thresholds configured for the badge rule \"{$ruleKey->value}\" must strictly increase from the lowest to the highest tier, got {$configured}.");
    }
}
