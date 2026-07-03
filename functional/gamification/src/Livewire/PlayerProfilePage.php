<?php

namespace Functional\Gamification\Livewire;

use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Services\LevelCurve;
use Functional\Gamification\Services\XpLedger;
use Functional\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

class PlayerProfilePage extends Component
{
    /**
     * Render the player profile with level ring, domain totals and daily xp chart.
     */
    #[Layout('layouts.app')]
    #[Title('Joueur')]
    public function render(LevelCurve $levelCurve, XpLedger $xpLedger): View
    {
        /** @var User $user */
        $user = Auth::user();

        $profile = PlayerProfile::query()->where('user_id', $user->id)->first();
        $totalXp = $profile?->total_xp ?? 0;
        $level = $profile?->level ?? 1;

        $levelFloor = $levelCurve->xpForLevel($level);
        $levelCeiling = $levelCurve->xpForLevel($level + 1);
        $levelSpan = max($levelCeiling - $levelFloor, 1);
        $levelPercentage = min(($totalXp - $levelFloor) / $levelSpan * 100, 100);

        $series = $xpLedger->dailySeries($user, 30);

        return view('gamification::player', [
            'level' => $level,
            'totalXp' => $totalXp,
            'remainingXp' => max($levelCeiling - $totalXp, 0),
            'levelPercentage' => $levelPercentage,
            'monthlyXp' => $xpLedger->gainedSince($user, now()->startOfMonth()),
            'weeklyXp' => $xpLedger->gainedSince($user, now()->subDays(7)),
            'domainTotals' => $xpLedger->totalsByDomain($user),
            'domains' => GamificationDomain::cases(),
            'chartLabels' => array_keys($series),
            'chartValues' => array_values($series),
        ]);
    }
}
