<?php

namespace Functional\Finance\Dashboard;

use Functional\Finance\Models\BankAccount;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class BankDashboardSummary implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M3 6h18M3 10h18M5 6V4h14v2M5 20h14a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2z';

    /**
     * Summarise the user's total bank balance across all GoCardless accounts.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $connected = IntegrationConnection::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('provider', IntegrationProvider::GoCardless)
            ->exists();

        $accounts = BankAccount::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->get();

        $totalBalance = $accounts->sum(fn (BankAccount $account): float => (float) $account->balance);
        $accountCount = $accounts->count();

        return new DashboardSummary(
            key: 'bank',
            title: 'Banque',
            accent: 'cyan',
            icon: self::ICON,
            href: route('finance'),
            order: 100,
            available: $connected,
            metricValue: number_format($totalBalance, 0, ',', ' '),
            metricUnit: '€',
            secondaryLines: [
                $accountCount.' compte'.($accountCount !== 1 ? 's' : ''),
            ],
            callToAction: $connected ? null : 'Connecter ma banque',
        );
    }

    /**
     * Expose the bank navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Banque', route: 'finance', icon: self::ICON, order: 100);
    }
}
