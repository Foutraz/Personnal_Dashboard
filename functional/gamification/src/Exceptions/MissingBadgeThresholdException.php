<?php

namespace Functional\Gamification\Exceptions;

use Functional\Gamification\Enums\BadgeTier;
use RuntimeException;

class MissingBadgeThresholdException extends RuntimeException
{
    public function __construct(public readonly string $ruleKey, public readonly BadgeTier $tier)
    {
        parent::__construct("No threshold is configured for the badge rule \"{$ruleKey}\" at the \"{$tier->value}\" tier.");
    }
}
