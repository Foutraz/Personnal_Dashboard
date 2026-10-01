<?php

namespace Functional\Gamification\Exceptions;

use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\BadgeTier;
use RuntimeException;

class MissingBadgeThresholdException extends RuntimeException
{
    public function __construct(public readonly BadgeRuleKey $ruleKey, public readonly BadgeTier $tier)
    {
        parent::__construct("No threshold is configured for the badge rule \"{$ruleKey->value}\" at the \"{$tier->value}\" tier.");
    }
}
