<?php

namespace Tests\Feature\Dashboard\Fixtures;

use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\NavigationItem;

class EarlierFakeDashboardContribution implements ProvidesNavigationItem
{
    /**
     * Build a deterministic navigation entry with a lower order to prove sorting.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'Earlier Fake', route: 'dashboard', icon: 'M0 0', order: 1);
    }
}
