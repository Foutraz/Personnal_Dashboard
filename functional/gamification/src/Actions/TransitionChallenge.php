<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Challenges\States\ChallengeState;
use Functional\Gamification\Exceptions\StaleChallengeStatusException;
use Functional\Gamification\Models\Challenge;

class TransitionChallenge
{
    private const SINGLE_ROW = 1;

    /**
     * Persist the transition of the challenge only if its status is still the one it was loaded with, and nothing when the next state keeps that status.
     *
     * @throws StaleChallengeStatusException
     */
    public function handle(Challenge $challenge, ChallengeState $next, ?float $currentValue = null): void
    {
        if ($next->status() === $challenge->status) {
            return;
        }

        $transitionedAt = now();
        $changes = ['status' => $next->status(), 'updated_at' => $transitionedAt];

        if ($next->isCommitment()) {
            $changes['accepted_at'] = $transitionedAt;
        }

        if ($next->isTerminal()) {
            $changes['resolved_at'] = $transitionedAt;
        }

        if ($currentValue !== null) {
            $changes['current_value'] = $currentValue;
        }

        $updatedRows = Challenge::query()
            ->whereKey($challenge->getKey())
            ->where('status', $challenge->status)
            ->update($changes);

        if ($updatedRows !== self::SINGLE_ROW) {
            throw StaleChallengeStatusException::for($challenge);
        }

        $challenge->forceFill($changes)->syncOriginal();
    }
}
