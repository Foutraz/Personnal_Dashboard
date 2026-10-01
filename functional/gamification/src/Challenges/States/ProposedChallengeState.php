<?php

namespace Functional\Gamification\Challenges\States;

use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Exceptions\IllegalChallengeTransitionException;
use Functional\Gamification\Services\Dto\ChallengeProgress;

final class ProposedChallengeState implements ChallengeState
{
    public function status(): ChallengeStatus
    {
        return ChallengeStatus::Proposed;
    }

    public function accept(): ChallengeState
    {
        return new AcceptedChallengeState;
    }

    public function decline(): ChallengeState
    {
        return new DeclinedChallengeState;
    }

    public function complete(): never
    {
        throw IllegalChallengeTransitionException::for($this, __FUNCTION__);
    }

    public function fail(): never
    {
        throw IllegalChallengeTransitionException::for($this, __FUNCTION__);
    }

    public function expire(): ChallengeState
    {
        return new ExpiredChallengeState;
    }

    public function evolve(ChallengeProgress $progress): ChallengeState
    {
        return $progress->weekEnded ? $this->expire() : $this;
    }

    public function awaitsResponse(): bool
    {
        return true;
    }

    public function isCommitment(): bool
    {
        return false;
    }

    public function isTerminal(): bool
    {
        return false;
    }
}
