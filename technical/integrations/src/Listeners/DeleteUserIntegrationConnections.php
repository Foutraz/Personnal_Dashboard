<?php

namespace Technical\Integrations\Listeners;

use Functional\Users\Events\UserDeleting;
use Technical\Integrations\Models\IntegrationConnection;

class DeleteUserIntegrationConnections
{
    /**
     * Delete the connections owned by the deleting user.
     */
    public function handle(UserDeleting $event): void
    {
        IntegrationConnection::query()
            ->whereBelongsTo($event->user)
            ->cursor()
            ->each(fn (IntegrationConnection $connection) => $connection->delete());
    }
}
