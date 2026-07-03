<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Services\Dto\LevelTransition;
use Functional\Gamification\Services\LevelCurve;
use Functional\Users\Models\User;

class RefreshPlayerProfile
{
    public function __construct(private LevelCurve $levelCurve) {}

    /**
     * Recompute the user profile projection from the ledger and report the level transition.
     */
    public function handle(User $user): LevelTransition
    {
        $totalXp = (int) XpEntry::query()->where('user_id', $user->id)->sum('points');
        $profile = PlayerProfile::query()->where('user_id', $user->id)->first();
        $previousLevel = $profile === null ? 1 : $profile->level;
        $level = $this->levelCurve->levelForXp($totalXp);

        $attributes = ['total_xp' => $totalXp, 'level' => $level];

        if ($level !== $previousLevel) {
            $attributes['level_reached_at'] = now();
        }

        PlayerProfile::query()->updateOrCreate(['user_id' => $user->id], $attributes);

        return new LevelTransition($previousLevel, $level);
    }
}
