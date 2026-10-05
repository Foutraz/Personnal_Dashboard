<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Exceptions\InvalidChallengeConfigException;
use Functional\Gamification\Exceptions\MissingChallengeTemplateConfigException;
use Functional\Gamification\Exceptions\StaleChallengeStatusException;
use Functional\Gamification\Services\Dto\ChallengeCycleOutcome;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Goals\Exceptions\UnboundedGoalMetricException;
use Functional\Users\Models\User;

class RunChallengeCycle
{
    public function __construct(
        private GamificationCalendar $calendar,
        private ProposeWeeklyChallenges $proposeWeeklyChallenges,
        private ResolveChallenges $resolveChallenges,
        private ReconvergeChallengeXp $reconvergeChallengeXp,
    ) {}

    /**
     * Propose the set of the current week, resolve the open challenges and reconverge the challenge ledger entries.
     *
     * @throws MissingChallengeTemplateConfigException|InvalidChallengeConfigException|StaleChallengeStatusException|UnboundedGoalMetricException
     */
    public function handle(User $user): ChallengeCycleOutcome
    {
        $week = $this->calendar->currentWeek();
        $proposed = $this->proposeWeeklyChallenges->handle($user, $week);
        $completed = $this->resolveChallenges->handle($user);
        $this->reconvergeChallengeXp->handle($user);

        return new ChallengeCycleOutcome($week, $proposed, $completed);
    }
}
