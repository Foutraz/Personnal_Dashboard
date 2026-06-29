<?php

namespace Functional\RecurringExpenses\Dashboard;

use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\RecurringExpenses\Services\MonthlyExpenseSummary;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class RecurringExpensesDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M7 3v3m10-3v3M4 9h16M5 6h14a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z';

    public function __construct(private MonthlyExpenseSummary $summary) {}

    /**
     * Summarise the user's monthly recurring cost.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $expenses = RecurringExpense::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('active', true)
            ->get();
        $monthly = $this->summary->build($expenses);

        return new DashboardSummary(
            key: 'deadlines',
            title: 'Échéances',
            accent: 'cyan',
            icon: self::ICON,
            href: route('recurring-expenses'),
            order: 60,
            available: true,
            metricValue: number_format($monthly->monthlyTotal, 0, ',', ' '),
            metricUnit: '€/mois',
            secondaryLines: [
                number_format($monthly->yearlyTotal, 0, ',', ' ').' €/an',
                $expenses->count().' charges actives',
            ],
        );
    }

    /**
     * Expose the recurring-expenses navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Échéances', route: 'recurring-expenses', icon: self::ICON, order: 60);
    }
}
