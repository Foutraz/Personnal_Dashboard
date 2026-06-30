<?php

namespace Technical\WebAuthentication\Services;

use Illuminate\Support\Collection;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\NavigationItem;

final class NavigationItemCollector
{
    /**
     * Collect every tagged module navigation entry, ordered ascending.
     *
     * @return Collection<int, NavigationItem>
     */
    public function all(): Collection
    {
        /** @var iterable<int, ProvidesNavigationItem> $providers */
        $providers = app()->tagged('dashboard.navigation');

        return collect($providers)
            ->map(fn (ProvidesNavigationItem $provider): NavigationItem => $provider->navigationItem())
            ->sortBy(fn (NavigationItem $item): int => $item->order)
            ->values();
    }
}
