<?php

namespace Functional\Gamification\Exceptions;

use DomainException;
use Functional\Gamification\Challenges\States\ChallengeState;
use Functional\Gamification\Enums\ChallengeStatus;

class IllegalChallengeTransitionException extends DomainException
{
    private function __construct(public readonly ChallengeStatus $status, public readonly string $operation)
    {
        parent::__construct("Cannot {$operation} a challenge that is {$status->value}.");
    }

    public static function for(ChallengeState $from, string $operation): self
    {
        return new self($from->status(), $operation);
    }
}
