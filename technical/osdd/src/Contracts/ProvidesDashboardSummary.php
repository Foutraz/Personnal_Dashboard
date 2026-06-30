<?php

namespace Technical\Osdd\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Dto\DashboardSummary;

interface ProvidesDashboardSummary
{
    /**
     * Build the dashboard summary tile scoped to the given user.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary;
}
