<?php

namespace Functional\Gamification\Exceptions;

use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\BadgeTier;
use RuntimeException;

class InvalidBadgeThresholdException extends RuntimeException
{
    public function __construct(public readonly BadgeRuleKey $ruleKey, public readonly BadgeTier $tier, public readonly mixed $threshold)
    {
        $givenType = get_debug_type($threshold);

        parent::__construct("The threshold configured for the badge rule \"{$ruleKey->value}\" at the \"{$tier->value}\" tier must be numeric, \"{$givenType}\" given.");
    }
}
