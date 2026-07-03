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
        $profile = PlayerProfile::query()->firstOrNew(['user_id' => $user->id]);
        $previousLevel = $profile->exists ? $profile->level : 1;
        $level = $this->levelCurve->levelForXp($totalXp);

        $profile->fill(['total_xp' => $totalXp, 'level' => $level]);

        if ($level !== $previousLevel) {
            $profile->level_reached_at = now();
        }

        $profile->save();

        return new LevelTransition($previousLevel, $level);
    }
}
