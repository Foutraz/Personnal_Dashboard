<?php

namespace Functional\Gamification\Challenges\States;

use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Exceptions\IllegalChallengeTransitionException;
use Functional\Gamification\Services\Dto\ChallengeProgress;

final class AcceptedChallengeState implements ChallengeState
{
    public function status(): ChallengeStatus
    {
        return ChallengeStatus::Accepted;
    }

    public function accept(): never
    {
        throw IllegalChallengeTransitionException::for($this, __FUNCTION__);
    }

    public function decline(): never
    {
        throw IllegalChallengeTransitionException::for($this, __FUNCTION__);
    }

    public function complete(): ChallengeState
    {
        return new CompletedChallengeState;
    }

    public function fail(): ChallengeState
    {
        return new FailedChallengeState;
    }

    public function expire(): never
    {
        throw IllegalChallengeTransitionException::for($this, __FUNCTION__);
    }

    public function evolve(ChallengeProgress $progress): ChallengeState
    {
        return match (true) {
            $progress->targetReached => $this->complete(),
            $progress->gracePassed => $this->fail(),
            default => $this,
        };
    }

    public function awaitsResponse(): bool
    {
        return false;
    }

    public function isCommitment(): bool
    {
        return true;
    }

    public function isTerminal(): bool
    {
        return false;
    }
}
