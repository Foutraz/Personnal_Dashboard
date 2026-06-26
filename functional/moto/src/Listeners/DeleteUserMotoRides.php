<?php

namespace Functional\Moto\Listeners;

use Functional\Moto\Models\MotoRide;
use Functional\Users\Events\UserDeleting;

class DeleteUserMotoRides
{
    /**
     * Delete the moto rides owned by the deleting user.
     */
    public function handle(UserDeleting $event): void
    {
        MotoRide::query()
            ->where('user_id', $event->user->id)
            ->cursor()
            ->each(fn (MotoRide $ride) => $ride->delete());
    }
}
