<?php

namespace Functional\Gamification\Services;

use Carbon\CarbonInterface;
use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Exceptions\UnboundedGoalMetricException;
use Functional\Goals\Services\GoalProgressCalculator;
use Functional\Users\Models\User;

class WeeklyMetricMeter
{
    public function __construct(private GoalProgressCalculator $calculator) {}

    /**
     * A Carbon zoned in Paris is formatted in local time by the query, which shifts the interval by the Paris offset.
     *
     * @throws UnboundedGoalMetricException
     */
    public function measure(User $user, GoalMetric $metric, CarbonInterface $startsAt, CarbonInterface $endsAt): float
    {
        $applicationTimezone = (string) config('app.timezone');

        return round($this->calculator->measure(
            $metric,
            $user->id,
            $startsAt->toImmutable()->setTimezone($applicationTimezone),
            $endsAt->toImmutable()->setTimezone($applicationTimezone)->subSecond(),
        ), 2);
    }

    /**
     * @throws UnboundedGoalMetricException
     */
    public function measureWeek(User $user, GoalMetric $metric, GamificationWeek $week): float
    {
        return $this->measure($user, $metric, $week->startsAt, $week->endsAt);
    }
}
