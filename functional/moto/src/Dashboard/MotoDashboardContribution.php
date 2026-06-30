<?php

namespace Functional\Moto\Dashboard;

use Functional\Moto\Models\MotoRide;
use Functional\Moto\Services\RidingStatsCalculator;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class MotoDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M3 15a4 4 0 0 0 4 4h9a4 4 0 0 0 0-8 6 6 0 0 0-11.7-1.8A4 4 0 0 0 3 15Z';

    public function __construct(private RidingStatsCalculator $calculator) {}

    /**
     * Summarise the user's total riding distance and ride count.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $rides = MotoRide::query()->where('user_id', $user->getAuthIdentifier())->get();

        return new DashboardSummary(
            key: 'moto',
            title: 'Météo & Moto',
            accent: 'violet',
            icon: self::ICON,
            href: route('moto'),
            order: 70,
            available: true,
            metricValue: number_format($this->calculator->totalDistance($rides), 0, ',', ' '),
            metricUnit: 'km',
            secondaryLines: [$this->calculator->rideCount($rides).' sorties'],
        );
    }

    /**
     * Expose the moto navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Météo & Moto', route: 'moto', icon: self::ICON, order: 70);
    }
}
