<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Contracts\XpRule;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Services\Dto\LevelTransition;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RunUserGamification
{
    public function __construct(
        private AwardXp $awardXp,
        private UpdateStreaks $updateStreaks,
        private RefreshPlayerProfile $refreshPlayerProfile,
    ) {}

    /**
     * Run every tagged xp rule then the streaks in one transaction and refresh the profile once, widening to the full history when the ledger is empty.
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

            return $this->refreshPlayerProfile->handle($user);
        });
    }
}
