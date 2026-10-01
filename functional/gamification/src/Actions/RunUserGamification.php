<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Contracts\XpRule;
use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Notifications\BadgeAwardedNotification;
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
        private RefreshPlayerProfile $refreshPlayerProfile,
    ) {}

    /**
     * Run every tagged xp rule, the streaks and the badges of the synced catalogue in one transaction, notify the new badges within it and refresh the profile once.
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
}
