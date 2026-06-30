<?php

namespace Functional\Sport\Dashboard;

use Functional\Sport\Models\SportActivity;
use Functional\Sport\Services\SportStatisticsCalculator;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class SportDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M4 7h3l2-3h6l2 3h3M5 7v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7M9 13a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z';

    public function __construct(private SportStatisticsCalculator $calculator) {}

    /**
     * Summarise the user's total distance and connection state.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $activities = SportActivity::query()->where('user_id', $user->getAuthIdentifier())->get();
        $connected = IntegrationConnection::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('provider', IntegrationProvider::Strava)
            ->exists();

        return new DashboardSummary(
            key: 'sport',
            title: 'Sport',
            accent: 'cyan',
            icon: self::ICON,
            href: route('sport'),
            order: 10,
            available: $connected,
            metricValue: number_format($this->calculator->totalDistance($activities) / 1000, 0, ',', ' '),
            metricUnit: 'km',
            secondaryLines: [
                $activities->count().' activités',
                number_format($this->calculator->totalElevation($activities), 0, ',', ' ').' m D+',
            ],
            callToAction: $connected ? null : 'Connecter Strava',
        );
    }

    /**
     * Expose the sport navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Sport', route: 'sport', icon: self::ICON, order: 10);
    }
}
