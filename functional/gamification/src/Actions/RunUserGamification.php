<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Contracts\XpRule;
use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Notifications\BadgeAwardedNotification;
use Functional\Gamification\Services\Dto\LevelTransition;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RunUserGamification
{
    public function __construct(
        private SyncBadgeCatalogue $syncBadgeCatalogue,
        private AwardXp $awardXp,
        private UpdateStreaks $updateStreaks,
        private EvaluateBadges $evaluateBadges,
        private RefreshPlayerProfile $refreshPlayerProfile,
    ) {}

    /**
     * Sync the badge catalogue, then run every tagged xp rule, the streaks and the badges in one transaction, refresh the profile once and notify the new badges after the commit.
     */
    public function handle(User $user, ?Carbon $since = null): LevelTransition
    {
        if ($since !== null && ! XpEntry::query()->whereBelongsTo($user)->exists()) {
            $since = null;
        }

        $windowStart = $since?->copy()->startOfDay();

        $this->syncBadgeCatalogue->handle();

        $rules = collect(app()->tagged('gamification.xp_rules'));
        $awards = $rules->flatMap(fn (XpRule $rule) => $rule->awards($user, $windowStart));
        $ruleKeys = $rules->map(fn (XpRule $rule): string => $rule->key())->values();

        /** @var Collection<int, BadgeAward> $newBadgeAwards */
        $newBadgeAwards = collect();

        $transition = DB::transaction(function () use ($user, $awards, $ruleKeys, $windowStart, &$newBadgeAwards): LevelTransition {
            $this->awardXp->handle($user, $awards, $ruleKeys, $windowStart);
            $this->updateStreaks->handle($user);
            $newBadgeAwards = $this->evaluateBadges->handle($user);

            return $this->refreshPlayerProfile->handle($user);
        });

        $newBadgeAwards->each(fn (BadgeAward $award) => $user->notify(new BadgeAwardedNotification($award->badge)));

        return $transition;
    }
}
