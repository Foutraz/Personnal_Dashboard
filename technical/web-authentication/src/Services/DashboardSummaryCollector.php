<?php

namespace Technical\WebAuthentication\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Dto\DashboardSummary;

final class DashboardSummaryCollector
{
    /**
     * Collect every tagged module summary scoped to the user, ordered ascending.
     *
     * @return Collection<int, DashboardSummary>
     */
    public function for(Authenticatable $user): Collection
    {
        /** @var iterable<int, ProvidesDashboardSummary> $providers */
        $providers = app()->tagged('dashboard.summaries');

        return collect($providers)
            ->map(fn (ProvidesDashboardSummary $provider): DashboardSummary => $provider->dashboardSummary($user))
            ->sortBy(fn (DashboardSummary $summary): int => $summary->order)
            ->values();
    }
}
