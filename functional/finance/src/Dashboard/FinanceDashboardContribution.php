<?php

namespace Functional\Finance\Dashboard;

use Functional\Finance\Models\Position;
use Functional\Finance\Services\PerformanceCalculator;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class FinanceDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M3 17l5-5 4 4 8-8M21 8v5h-5';

    public function __construct(private PerformanceCalculator $calculator) {}

    /**
     * Summarise the user's global portfolio performance.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $positions = Position::query()->where('user_id', $user->getAuthIdentifier())->get();
        $performance = $this->calculator->globalPerformance($positions);
        $sign = $performance->percentageGain >= 0.0 ? '+' : '';

        return new DashboardSummary(
            key: 'finance',
            title: 'Finance',
            accent: 'lime',
            icon: self::ICON,
            href: route('finance'),
            order: 20,
            available: true,
            metricValue: $sign.number_format($performance->percentageGain, 1, ',', ' '),
            metricUnit: '%',
            secondaryLines: [
                number_format($performance->currentValue, 0, ',', ' ').' €',
                'Investi '.number_format($performance->netInvested, 0, ',', ' ').' €',
            ],
        );
    }

    /**
     * Expose the finance navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Finance', route: 'finance', icon: self::ICON, order: 20);
    }
}
