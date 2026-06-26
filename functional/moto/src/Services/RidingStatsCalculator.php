<?php

namespace Functional\Moto\Services;

use Functional\Moto\Models\MotoRide;
use Illuminate\Support\Collection;

class RidingStatsCalculator
{
    /**
     * Sum the distance of the given rides in kilometers.
     *
     * @param  Collection<int, MotoRide>  $rides
     */
    public function totalDistance(Collection $rides): float
    {
        return (float) $rides->sum(fn (MotoRide $ride): float => (float) $ride->distance);
    }

    /**
     * Sum the duration of the given rides in seconds.
     *
     * @param  Collection<int, MotoRide>  $rides
     */
    public function totalDuration(Collection $rides): int
    {
        return (int) $rides->sum('duration');
    }

    /**
     * Count the given rides.
     *
     * @param  Collection<int, MotoRide>  $rides
     */
    public function rideCount(Collection $rides): int
    {
        return $rides->count();
    }

    /**
     * Compute the average distance per ride in kilometers.
     *
     * @param  Collection<int, MotoRide>  $rides
     */
    public function averageDistance(Collection $rides): float
    {
        if ($rides->isEmpty()) {
            return 0.0;
        }

        return $this->totalDistance($rides) / $rides->count();
    }

    /**
     * Compute the average speed across the given rides in kilometers per hour.
     *
     * @param  Collection<int, MotoRide>  $rides
     */
    public function averageSpeed(Collection $rides): float
    {
        $duration = $this->totalDuration($rides);

        if ($duration <= 0) {
            return 0.0;
        }

        return $this->totalDistance($rides) / ($duration / 3600);
    }
}
