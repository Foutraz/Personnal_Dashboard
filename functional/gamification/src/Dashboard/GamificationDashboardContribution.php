<?php

namespace Functional\Gamification\Dashboard;

use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Gamification\Services\LevelCurve;
use Functional\Gamification\Services\XpLedger;
use Functional\Users\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class GamificationDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M8 21h8m-4-4v4M6 4h12v5a6 6 0 0 1-12 0V4Zm12 2h3a3 3 0 0 1-3 4M6 6H3a3 3 0 0 0 3 4';

    public function __construct(
        private LevelCurve $levelCurve,
        private XpLedger $xpLedger,
        private GamificationCalendar $calendar,
    ) {}

    /**
     * Summarise the user's player level and experience.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        /** @var User $user */
        $profile = PlayerProfile::query()->whereBelongsTo($user)->first();
        $totalXp = $profile === null ? 0 : $profile->total_xp;
        $level = $profile === null ? 1 : $profile->level;
        $remaining = max($this->levelCurve->xpForLevel($level + 1) - $totalXp, 0);
        $monthlyXp = $this->xpLedger->gainedSince($user, now()->startOfMonth());

        $hottest = Streak::query()
            ->whereBelongsTo($user)
            ->where('current_count', '>', 0)
            ->orderByDesc('current_count')
            ->get()
            ->first(fn (Streak $streak): bool => $this->calendar->isStreakAlive($streak->last_activity_date));

        $secondaryLines = [
            __('gamification::dashboard.total_xp', ['xp' => number_format($totalXp, 0, ',', ' ')]),
            __('gamification::dashboard.remaining_xp', ['xp' => number_format($remaining, 0, ',', ' '), 'level' => $level + 1]),
            __('gamification::dashboard.monthly_xp', ['xp' => number_format($monthlyXp, 0, ',', ' ')]),
        ];

        if ($hottest !== null) {
            $secondaryLines[] = __('gamification::dashboard.hottest_streak', ['domain' => $hottest->domain->label(), 'count' => $hottest->current_count]);
        }

        return new DashboardSummary(
            key: 'gamification',
            title: __('gamification::dashboard.title'),
            accent: 'violet',
            icon: self::ICON,
            href: route('player'),
            order: 45,
            available: true,
            metricValue: number_format($level, 0, ',', ' '),
            metricUnit: __('gamification::dashboard.metric_unit'),
            secondaryLines: $secondaryLines,
        );
    }

    /**
     * Expose the player navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: __('gamification::dashboard.title'), route: 'player', icon: self::ICON, order: 45);
    }
}
