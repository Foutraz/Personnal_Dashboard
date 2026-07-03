<?php

namespace Functional\Gamification\Listeners;

use Functional\Exploration\Events\CoverageRebuilt;
use Functional\Finance\Events\BankTransactionsSynced;
use Functional\Gamification\Jobs\ProcessUserGamificationJob;
use Functional\Health\Events\WithingsMeasurementsSynced;
use Functional\Sport\Events\StravaActivitiesSynced;

class ProcessXpOnSync
{
    /**
     * Process the synced user's gamification over a short overlapping window.
     */
    public function handle(StravaActivitiesSynced|WithingsMeasurementsSynced|BankTransactionsSynced|CoverageRebuilt $event): void
    {
        ProcessUserGamificationJob::dispatch($event->userId, now()->subDays(7));
    }
}
