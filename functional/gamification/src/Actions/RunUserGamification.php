<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Contracts\XpRule;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Services\Dto\LevelTransition;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;

class RunUserGamification
{
    public function __construct(private AwardXp $awardXp) {}

    /**
     * Run every tagged xp rule for the user, widening to the full history when the ledger is empty.
     */
    public function handle(User $user, ?Carbon $since = null): LevelTransition
    {
        if ($since !== null && ! XpEntry::query()->where('user_id', $user->id)->exists()) {
            $since = null;
        }

        $windowStart = $since?->copy()->startOfDay();

        $rules = collect(app()->tagged('gamification.xp_rules'));
        $awards = $rules->flatMap(fn (XpRule $rule) => $rule->awards($user, $windowStart));
        $ruleKeys = $rules->map(fn (XpRule $rule): string => $rule->key())->values();

        return $this->awardXp->handle($user, $awards, $ruleKeys, $windowStart);
    }
}
