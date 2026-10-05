<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Contracts\XpRule;
use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Notifications\BadgeAwardedNotification;
use Functional\Gamification\Notifications\ChallengeCompletedNotification;
use Functional\Gamification\Notifications\ChallengesProposedNotification;
use Functional\Gamification\Services\Dto\ChallengeCycleOutcome;
use Functional\Gamification\Services\Dto\LevelTransition;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RunUserGamification
{
    public function __construct(
        private AwardXp $awardXp,
        private UpdateStreaks $updateStreaks,
        private EvaluateBadges $evaluateBadges,
        private RunChallengeCycle $runChallengeCycle,
        private RefreshPlayerProfile $refreshPlayerProfile,
    ) {}

    /**
     * Run every tagged xp rule, the streaks, the badges of the synced catalogue and the challenge cycle in one transaction, notify the new badges and challenges within it and refresh the profile once.
     */
    public function handle(User $user, ?Carbon $since = null): LevelTransition
    {
        if ($since !== null && ! XpEntry::query()->whereBelongsTo($user)->exists()) {
            $since = null;
        }

        $windowStart = $since?->copy()->startOfDay();

        $rules = collect(app()->tagged('gamification.xp_rules'));
        $awards = $rules->flatMap(fn (XpRule $rule) => $rule->awards($user, $windowStart));
        $ruleKeys = $rules->map(fn (XpRule $rule): string => $rule->key())->values();

        return DB::transaction(function () use ($user, $awards, $ruleKeys, $windowStart): LevelTransition {
            $this->awardXp->handle($user, $awards, $ruleKeys, $windowStart);
            $this->updateStreaks->handle($user);
            $this->notifyBadges($user, $this->evaluateBadges->handle($user));
            $this->notifyChallenges($user, $this->runChallengeCycle->handle($user));

            return $this->refreshPlayerProfile->handle($user);
        });
    }

    /**
     * Notify the user of each new badge inside the run transaction, where a queued, mail or broadcast channel announces badges that a rollback removes.
     *
     * @param  Collection<int, BadgeAward>  $newBadgeAwards
     */
    private function notifyBadges(User $user, Collection $newBadgeAwards): void
    {
        $newBadgeAwards->each(fn (BadgeAward $award) => $user->notify(new BadgeAwardedNotification($award->badge)));
    }

    /**
     * Notify the user of the proposed set and of each completed challenge inside the run transaction, where a mail, broadcast or queued channel announces challenges that a rollback removes.
     */
    private function notifyChallenges(User $user, ChallengeCycleOutcome $outcome): void
    {
        if ($outcome->proposed->isNotEmpty()) {
            $user->notify(new ChallengesProposedNotification($outcome->week, $outcome->proposed->count()));
        }

        $outcome->completed->each(fn (Challenge $challenge) => $user->notify(new ChallengeCompletedNotification($challenge)));
    }
}
