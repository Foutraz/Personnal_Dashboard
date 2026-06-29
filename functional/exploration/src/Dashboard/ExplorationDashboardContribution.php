<?php

namespace Functional\Exploration\Dashboard;

use Functional\Exploration\Models\ExploredCell;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class ExplorationDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M9 6 3 4v14l6 2 6-2 6 2V6l-6-2-6 2Zm0 0v14m6-12v14';

    /**
     * Summarise the user's explored grid cell count.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $cells = ExploredCell::query()->where('user_id', $user->getAuthIdentifier())->count();

        return new DashboardSummary(
            key: 'maps',
            title: 'Cartes',
            accent: 'violet',
            icon: self::ICON,
            href: route('exploration'),
            order: 80,
            available: true,
            metricValue: number_format($cells, 0, ',', ' '),
            metricUnit: 'cellules',
            secondaryLines: [$cells.' cellules explorées'],
        );
    }

    /**
     * Expose the exploration navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Cartes', route: 'exploration', icon: self::ICON, order: 80);
    }
}
