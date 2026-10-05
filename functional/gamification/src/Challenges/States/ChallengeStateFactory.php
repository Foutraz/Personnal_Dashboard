<?php

namespace Functional\Gamification\Challenges\States;

use Functional\Gamification\Enums\ChallengeStatus;

final class ChallengeStateFactory
{
    public static function fromStatus(ChallengeStatus $status): ChallengeState
    {
        return match ($status) {
            ChallengeStatus::Proposed => new ProposedChallengeState,
            ChallengeStatus::Accepted => new AcceptedChallengeState,
            ChallengeStatus::Completed => new CompletedChallengeState,
            ChallengeStatus::Failed => new FailedChallengeState,
            ChallengeStatus::Declined => new DeclinedChallengeState,
            ChallengeStatus::Expired => new ExpiredChallengeState,
        };
    }
}
