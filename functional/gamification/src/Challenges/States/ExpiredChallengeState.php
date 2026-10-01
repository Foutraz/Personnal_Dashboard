<?php

namespace Functional\Gamification\Challenges\States;

use Functional\Gamification\Enums\ChallengeStatus;

final class ExpiredChallengeState implements ChallengeState
{
    use RefusesChallengeTransitions;

    public function status(): ChallengeStatus
    {
        return ChallengeStatus::Expired;
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
