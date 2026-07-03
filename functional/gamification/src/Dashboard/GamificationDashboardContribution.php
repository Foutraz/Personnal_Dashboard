<?php

namespace Functional\Gamification\Dashboard;

use Functional\Gamification\Models\PlayerProfile;
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
    ) {}

    /**
     * Summarise the user's player level and experience.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $profile = PlayerProfile::query()->where('user_id', $user->getAuthIdentifier())->first();
        $totalXp = $profile === null ? 0 : $profile->total_xp;
        $level = $profile === null ? 1 : $profile->level;
        $remaining = max($this->levelCurve->xpForLevel($level + 1) - $totalXp, 0);
        /** @var User $user */
        $monthlyXp = $this->xpLedger->gainedSince($user, now()->startOfMonth());

        return new DashboardSummary(
            key: 'gamification',
            title: 'Joueur',
            accent: 'violet',
            icon: self::ICON,
            href: route('player'),
            order: 45,
            available: true,
            metricValue: number_format($level, 0, ',', ' '),
            metricUnit: 'niv.',
            secondaryLines: [
                number_format($totalXp, 0, ',', ' ').' XP au total',
                number_format($remaining, 0, ',', ' ').' XP avant le niveau '.($level + 1),
                number_format($monthlyXp, 0, ',', ' ').' XP ce mois-ci',
            ],
        );
    }

    /**
     * Expose the player navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Joueur', route: 'player', icon: self::ICON, order: 45);
    }
}
