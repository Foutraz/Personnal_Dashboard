<?php

namespace Functional\Sport\Listeners;

use Functional\Sport\Models\SportActivity;
use Technical\Integrations\Models\IntegrationConnection;

class DeleteConnectionSportActivities
{
    /**
     * Delete the activities owned by the deleting connection.
     */
    public function handle(IntegrationConnection $connection): void
    {
        SportActivity::query()
            ->where('integration_connection_id', $connection->id)
            ->cursor()
            ->each(fn (SportActivity $activity) => $activity->delete());
    }
}
