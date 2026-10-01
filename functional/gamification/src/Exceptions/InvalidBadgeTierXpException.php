<?php

namespace Functional\Gamification\Exceptions;

use Functional\Gamification\Enums\BadgeTier;
use RuntimeException;

class InvalidBadgeTierXpException extends RuntimeException
{
    public function __construct(public readonly BadgeTier $tier, public readonly mixed $xpReward)
    {
        $givenType = get_debug_type($xpReward);

        parent::__construct("The xp reward configured for the \"{$tier->value}\" badge tier must be a positive integer, \"{$givenType}\" given.");
    }
}
