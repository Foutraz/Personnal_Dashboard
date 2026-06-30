<?php

namespace Functional\Goals\Dashboard;

use Functional\Goals\Models\Goal;
use Functional\Goals\Services\Dto\GoalProgress;
use Functional\Goals\Services\GoalProgressCalculator;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class GoalsDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M12 12a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm0 0a9 9 0 1 1 0 18 9 9 0 0 1 0-18Zm0 6v0M12 3v3';

    public function __construct(private GoalProgressCalculator $calculator) {}

    /**
     * Summarise the user's average goal progress.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $goals = Goal::query()->where('user_id', $user->getAuthIdentifier())->get();
        $progresses = $goals->map(fn (Goal $goal): GoalProgress => $this->calculator->progress($goal));
        $average = $progresses->avg(fn (GoalProgress $progress): float => $progress->clampedPercentage()) ?? 0.0;
        $onTrack = $progresses->filter(fn (GoalProgress $progress): bool => $progress->onTrack)->count();

        return new DashboardSummary(
            key: 'goals',
            title: 'Objectifs',
            accent: 'cyan',
            icon: self::ICON,
            href: route('goals'),
            order: 40,
            available: true,
            metricValue: number_format($average, 0, ',', ' '),
            metricUnit: '%',
            secondaryLines: [
                $goals->count().' objectifs',
                $onTrack.' en bonne voie',
            ],
        );
    }

    /**
     * Expose the goals navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Objectifs', route: 'goals', icon: self::ICON, order: 40);
    }
}
