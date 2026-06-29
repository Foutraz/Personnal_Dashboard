<?php

namespace Tests\Feature\Dashboard\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

class FakeDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    /**
     * Build a deterministic summary used to prove tag auto-discovery.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        return new DashboardSummary(
            key: 'fake',
            title: 'Fake',
            accent: 'cyan',
            icon: 'M0 0',
            href: '#',
            order: 5,
            available: true,
            metricValue: '42',
        );
    }

    /**
     * Build a deterministic navigation entry used to prove tag auto-discovery.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Fake', route: 'dashboard', icon: 'M0 0', order: 5);
    }
}
