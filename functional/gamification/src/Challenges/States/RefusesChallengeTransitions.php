<?php

namespace Functional\Gamification\Challenges\States;

use Functional\Gamification\Exceptions\IllegalChallengeTransitionException;
use Functional\Gamification\Services\Dto\ChallengeProgress;

trait RefusesChallengeTransitions
{
    public function accept(): never
    {
        throw IllegalChallengeTransitionException::for($this, __FUNCTION__);
    }

    public function decline(): never
    {
        throw IllegalChallengeTransitionException::for($this, __FUNCTION__);
    }

    public function complete(): never
    {
        throw IllegalChallengeTransitionException::for($this, __FUNCTION__);
    }

    public function fail(): never
    {
        throw IllegalChallengeTransitionException::for($this, __FUNCTION__);
    }

    public function expire(): never
    {
        throw IllegalChallengeTransitionException::for($this, __FUNCTION__);
    }

    public function evolve(ChallengeProgress $progress): never
    {
        throw IllegalChallengeTransitionException::for($this, __FUNCTION__);
    }
}
