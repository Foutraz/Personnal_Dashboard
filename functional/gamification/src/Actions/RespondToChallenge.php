<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Exceptions\ChallengeNotRespondableException;
use Functional\Gamification\Exceptions\StaleChallengeStatusException;
use Functional\Gamification\Jobs\ProcessUserGamificationJob;
use Functional\Gamification\Models\Challenge;

class RespondToChallenge
{
    public function __construct(private TransitionChallenge $transitionChallenge) {}

    /**
     * @throws ChallengeNotRespondableException|StaleChallengeStatusException
     */
    public function accept(Challenge $challenge): void
    {
        $this->ensureRespondable($challenge);

        $this->transitionChallenge->handle($challenge, $challenge->state()->accept());

        ProcessUserGamificationJob::dispatch($challenge->user_id, now()->subDay());
    }

    /**
     * @throws ChallengeNotRespondableException|StaleChallengeStatusException
     */
    public function decline(Challenge $challenge): void
    {
        $this->ensureRespondable($challenge);

        $this->transitionChallenge->handle($challenge, $challenge->state()->decline());
    }

    public function isRespondable(Challenge $challenge): bool
    {
        return $challenge->state()->awaitsResponse() && now()->lt($challenge->ends_at);
    }

    /**
     * @throws ChallengeNotRespondableException
     */
    private function ensureRespondable(Challenge $challenge): void
    {
        if (! $this->isRespondable($challenge)) {
            throw new ChallengeNotRespondableException;
        }
    }
}
