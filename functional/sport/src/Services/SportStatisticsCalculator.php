<?php

namespace Functional\Sport\Services;

use Functional\Sport\Enums\EvolutionPeriod;
use Functional\Sport\Models\SportActivity;
use Illuminate\Support\Collection;

class SportStatisticsCalculator
{
    /**
     * Sum the distance of the given activities in meters.
     *
     * @param  Collection<int, SportActivity>  $activities
     */
    public function totalDistance(Collection $activities): float
    {
        return (float) $activities->sum('distance');
    }

    /**
     * Sum the elevation gain of the given activities in meters.
     *
     * @param  Collection<int, SportActivity>  $activities
     */
    public function totalElevation(Collection $activities): float
    {
        return (float) $activities->sum('total_elevation_gain');
    }

    /**
     * Sum the moving time of the given activities in seconds.
     *
     * @param  Collection<int, SportActivity>  $activities
     */
    public function totalMovingTime(Collection $activities): int
    {
        return (int) $activities->sum('moving_time');
    }

    /**
     * Count the activities grouped by their sport type label.
     *
     * @param  Collection<int, SportActivity>  $activities
     * @return array<string, int>
     */
    public function countByType(Collection $activities): array
    {
        return $activities
            ->groupBy(fn (SportActivity $activity): string => $activity->sport_type->label())
            ->map(fn (Collection $group): int => $group->count())
            ->all();
    }

    /**
     * Aggregate the cumulative distance of activities bucketed by period.
     *
     * @param  Collection<int, SportActivity>  $activities
     * @return array<string, float>
     */
    public function evolutionByPeriod(Collection $activities, EvolutionPeriod $period): array
    {
        return $activities
            ->sortBy(fn (SportActivity $activity): int => $activity->started_at->getTimestamp())
            ->groupBy(fn (SportActivity $activity): string => $activity->started_at->format($period->format()))
            ->map(fn (Collection $group): float => (float) $group->sum('distance'))
            ->all();
    }

    /**
     * Compute the average pace in seconds per kilometer across activities.
     *
     * @param  Collection<int, SportActivity>  $activities
     */
    public function averagePace(Collection $activities): float
    {
        $distance = $this->totalDistance($activities);

        if ($distance <= 0.0) {
            return 0.0;
        }

        return $this->totalMovingTime($activities) / ($distance / 1000);
    }
}
