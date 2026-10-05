<?php

namespace Functional\Moto\Listeners;

use Functional\Moto\Models\MotoRide;

class StampRideRecording
{
    /** A ride created with a preset recording instant keeps it, so an unguarded writer can backdate it. */
    public function handle(MotoRide $ride): void
    {
        if (! $ride->exists) {
            $ride->recorded_at ??= now();

            return;
        }

        if ($ride->isDirty(['started_at', 'distance'])) {
            $ride->recorded_at = now();
        }
    }
}
