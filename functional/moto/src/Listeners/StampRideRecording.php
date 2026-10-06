<?php

namespace Functional\Moto\Listeners;

use Functional\Moto\Models\MotoRide;

class StampRideRecording
{
    public function handle(MotoRide $ride): void
    {
        if (! $ride->exists || $ride->isDirty(['started_at', 'distance'])) {
            $ride->recorded_at = now();
        }
    }
}
