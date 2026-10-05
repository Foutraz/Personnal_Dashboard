<?php

namespace Functional\Gamification\Services;

use Carbon\CarbonInterface;
use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Exceptions\UnaggregatableGoalMetricException;
use Functional\Goals\Exceptions\UnboundedGoalMetricException;
use Functional\Goals\Services\Dto\MeasurementPeriod;
use Functional\Goals\Services\GoalMetricAggregator;
use Functional\Users\Models\User;

class WeeklyMetricMeter
{
    private const DECIMALS = 2;

    public function __construct(private GoalMetricAggregator $aggregator) {}

    /**
     * @throws UnboundedGoalMetricException|UnaggregatableGoalMetricException
     */
    public function measure(User $user, GoalMetric $metric, CarbonInterface $startsAt, CarbonInterface $endsAt, CarbonInterface $closesAt): float
    {
        $period = $this->periodOf($metric, $startsAt, $endsAt, $closesAt);

        return round($this->aggregator->totalsPerPeriod($metric, $user->id, [$period])[0], self::DECIMALS);
    }

    /**
     * @param  list<GamificationWeek>  $weeks
     * @return list<float>
     *
     * @throws UnboundedGoalMetricException|UnaggregatableGoalMetricException
     */
    public function history(User $user, GoalMetric $metric, array $weeks, int $closingGraceHours): array
    {
        $periods = array_map(
            fn (GamificationWeek $week): MeasurementPeriod => $this->periodOf($metric, $week->startsAt, $week->endsAt, $week->closesAt($closingGraceHours)),
            $weeks,
        );

        return array_map(
            fn (float $total): float => round($total, self::DECIMALS),
            $this->aggregator->totalsPerPeriod($metric, $user->id, $periods),
        );
    }

    private function periodOf(GoalMetric $metric, CarbonInterface $startsAt, CarbonInterface $endsAt, CarbonInterface $closesAt): MeasurementPeriod
    {
        return new MeasurementPeriod($startsAt, $endsAt, $metric->isSelfReported() ? $closesAt : null);
    }
}
