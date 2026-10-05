<?php

namespace Functional\Gamification\Challenges\States;

use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Exceptions\IllegalChallengeTransitionException;
use Functional\Gamification\Services\Dto\ChallengeProgress;

interface ChallengeState
{
    public function status(): ChallengeStatus;

    /**
     * @throws IllegalChallengeTransitionException
     */
    public function accept(): ChallengeState;

    /**
     * @throws IllegalChallengeTransitionException
     */
    public function decline(): ChallengeState;

    /**
     * @throws IllegalChallengeTransitionException
     */
    public function complete(): ChallengeState;

    /**
     * @throws IllegalChallengeTransitionException
     */
    public function fail(): ChallengeState;

    /**
     * @throws IllegalChallengeTransitionException
     */
    public function expire(): ChallengeState;

    /**
     * Apply the time rule of the state to the progress of the week, returning the state itself when nothing changes.
     *
     * @throws IllegalChallengeTransitionException
     */
    public function evolve(ChallengeProgress $progress): ChallengeState;

    public function awaitsResponse(): bool;

    public function isCommitment(): bool;

    public function isTerminal(): bool;
}
