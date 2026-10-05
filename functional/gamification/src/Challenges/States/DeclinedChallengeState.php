<?php

namespace Functional\Gamification\Challenges\States;

use Functional\Gamification\Enums\ChallengeStatus;

final class DeclinedChallengeState implements ChallengeState
{
    use RefusesChallengeTransitions;

    public function status(): ChallengeStatus
    {
        return ChallengeStatus::Declined;
    }

    public function awaitsResponse(): bool
    {
        return false;
    }

    public function isCommitment(): bool
    {
        return false;
    }

    public function isTerminal(): bool
    {
        return true;
    }
}
