<?php

namespace Functional\Gamification\Livewire;

use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Services\BadgeShowcase;
use Functional\Gamification\Services\Dto\StreakCard;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Gamification\Services\LevelCurve;
use Functional\Gamification\Services\XpLedger;
use Functional\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class PlayerProfilePage extends Component
{
    private const DAILY_SERIES_DAYS = 30;

    /**
     * Render the player profile with level ring, domain totals, daily xp chart and badge showcase.
     */
    #[Layout('layouts.app')]
    public function render(LevelCurve $levelCurve, XpLedger $xpLedger, GamificationCalendar $calendar, BadgeShowcase $badgeShowcase): View
    {
        /** @var User $user */
        $user = Auth::user();

        $profile = PlayerProfile::query()->whereBelongsTo($user)->first();
        $totalXp = $profile === null ? 0 : $profile->total_xp;
        $level = $profile === null ? 1 : $profile->level;

        $levelFloor = $levelCurve->xpForLevel($level);
        $levelCeiling = $levelCurve->xpForLevel($level + 1);
        $levelSpan = max($levelCeiling - $levelFloor, 1);
        $levelPercentage = min(($totalXp - $levelFloor) / $levelSpan * 100, 100);

        $series = $xpLedger->dailySeries($user, self::DAILY_SERIES_DAYS);

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
            'dailySeriesDays' => self::DAILY_SERIES_DAYS,
            'streakCards' => Streak::query()
                ->whereBelongsTo($user)
                ->orderByDesc('current_count')
                ->get()
                ->map(fn (Streak $streak): StreakCard => StreakCard::fromStreak($streak, $calendar->isStreakAlive($streak->last_activity_date))),
            'badgeFamilies' => $badgeShowcase->families($user),
            'badgesEarned' => $badgeShowcase->earnedCount($user),
            'badgesTotal' => $badgeShowcase->totalCount(),
        ])->title(__('gamification::player.title'));
    }
}
