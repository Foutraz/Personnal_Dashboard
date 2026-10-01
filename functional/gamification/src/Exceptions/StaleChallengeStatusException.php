<?php

namespace Functional\Gamification\Exceptions;

use DomainException;
use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Models\Challenge;

class StaleChallengeStatusException extends DomainException
{
    private function __construct(public readonly string $challengeId, public readonly ChallengeStatus $expectedStatus)
    {
        parent::__construct("The challenge {$challengeId} is no longer {$expectedStatus->value}.");
    }

    public static function for(Challenge $challenge): self
    {
        return new self($challenge->id, $challenge->status);
    }
}
