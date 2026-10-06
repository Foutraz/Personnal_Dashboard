<?php

namespace Functional\Goals\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Functional\Exploration\Models\ExploredCell;
use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Exceptions\UnaggregatableGoalMetricException;
use Functional\Goals\Exceptions\UnboundedGoalMetricException;
use Functional\Goals\Services\Dto\MeasurementPeriod;
use Functional\Goals\Services\Dto\MetricAggregate;
use Functional\Moto\Models\MotoRide;
use Functional\Sport\Models\SportActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use stdClass;

class GoalMetricAggregator
{
    /**
     * A zoned Carbon would shift the period, so bounds are converted to the application timezone before binding.
     *
     * @throws UnboundedGoalMetricException|UnaggregatableGoalMetricException
     */
    public function total(GoalMetric $metric, string $userId, ?CarbonInterface $from, ?CarbonInterface $until): float
    {
        $aggregate = $this->aggregateOf($metric);
        $query = $this->userRows($aggregate, $userId);

        if ($from !== null) {
            $query->where($aggregate->dateColumn, '>=', $this->inApplicationTimezone($from));
        }

        if ($until !== null) {
            $query->where($aggregate->dateColumn, '<=', $this->inApplicationTimezone($until));
        }

        $baseQuery = $query->toBase();
        $summed = $baseQuery
            ->selectRaw("{$aggregate->expression($baseQuery->getGrammar())} as total")
            ->value('total');

        return (float) $summed / $aggregate->divisor;
    }

    /**
     * A row inside overlapping periods is counted in the first period that accepts it, and a zoned Carbon would shift its period, so bounds are converted to the application timezone before binding.
     *
     * @param  array<array-key, MeasurementPeriod>  $periods
     * @return list<float>
     *
     * @throws UnboundedGoalMetricException|UnaggregatableGoalMetricException
     */
    public function totalsPerPeriod(GoalMetric $metric, string $userId, array $periods): array
    {
        $periods = array_values($periods);
        $aggregate = $this->aggregateOf($metric);

        if ($periods === []) {
            return [];
        }

        $baseQuery = $this->userRows($aggregate, $userId)
            ->where($aggregate->dateColumn, '>=', $this->inApplicationTimezone($this->earliestStart($periods)))
            ->where($aggregate->dateColumn, '<', $this->inApplicationTimezone($this->latestEnd($periods)))
            ->toBase();

        $totalsByIndex = $this->selectBucketedTotals($baseQuery, $aggregate, $periods)
            ->get()
            ->filter(fn (stdClass $row): bool => $row->period_index !== null)
            ->mapWithKeys(fn (stdClass $row): array => [(int) $row->period_index => (float) $row->total / $aggregate->divisor]);

        return array_map(
            fn (int $index): float => $totalsByIndex->get($index, 0.0),
            array_keys($periods),
        );
    }

    /**
     * @throws UnboundedGoalMetricException|UnaggregatableGoalMetricException
     */
    private function aggregateOf(GoalMetric $metric): MetricAggregate
    {
        return match ($metric) {
            GoalMetric::SportDistance => new MetricAggregate(SportActivity::class, 'started_at', 'distance', 1000.0),
            GoalMetric::SportElevation => new MetricAggregate(SportActivity::class, 'started_at', 'total_elevation_gain', 1.0),
            GoalMetric::SportActivityCount => new MetricAggregate(SportActivity::class, 'started_at', null, 1.0),
            GoalMetric::SportMovingTime => new MetricAggregate(SportActivity::class, 'started_at', 'moving_time', 3600.0),
            GoalMetric::MotoDistance => new MetricAggregate(MotoRide::class, 'started_at', 'distance', 1.0, 'recorded_at'),
            GoalMetric::MotoRideCount => new MetricAggregate(MotoRide::class, 'started_at', null, 1.0, 'recorded_at'),
            GoalMetric::ExplorationCells => new MetricAggregate(ExploredCell::class, 'first_seen_at', null, 1.0),
            GoalMetric::FinanceInvestedCapital => throw new UnaggregatableGoalMetricException($metric),
            GoalMetric::FinancePortfolioValue, GoalMetric::Manual, GoalMetric::TodoCompletionRate => throw new UnboundedGoalMetricException($metric),
        };
    }

    /**
     * @return Builder<Model>
     */
    private function userRows(MetricAggregate $aggregate, string $userId): Builder
    {
        return $aggregate->model::query()->where('user_id', $userId);
    }

    /**
     * @param  list<MeasurementPeriod>  $periods
     */
    private function selectBucketedTotals(QueryBuilder $baseQuery, MetricAggregate $aggregate, array $periods): QueryBuilder
    {
        $grammar = $baseQuery->getGrammar();
        $branches = [];
        $bindings = [];

        foreach ($periods as $index => $period) {
            $tests = [
                "{$grammar->wrap($aggregate->dateColumn)} >= ?",
                "{$grammar->wrap($aggregate->dateColumn)} < ?",
            ];
            $bindings[] = $this->inApplicationTimezone($period->startsAt);
            $bindings[] = $this->inApplicationTimezone($period->endsAt);

            if ($aggregate->recordedColumn !== null && $period->recordedBefore !== null) {
                $tests[] = "{$grammar->wrap($aggregate->recordedColumn)} < ?";
                $bindings[] = $this->inApplicationTimezone($period->recordedBefore);
            }

            $branches[] = 'when '.implode(' and ', $tests)." then {$index}";
        }

        return $baseQuery
            ->selectRaw('case '.implode(' ', $branches).' end as period_index', $bindings)
            ->selectRaw("{$aggregate->expression($grammar)} as total")
            ->groupBy('period_index');
    }

    /**
     * @param  list<MeasurementPeriod>  $periods
     */
    private function earliestStart(array $periods): CarbonInterface
    {
        return collect($periods)->map(fn (MeasurementPeriod $period): CarbonInterface => $period->startsAt)->min();
    }

    /**
     * @param  list<MeasurementPeriod>  $periods
     */
    private function latestEnd(array $periods): CarbonInterface
    {
        return collect($periods)->map(fn (MeasurementPeriod $period): CarbonInterface => $period->endsAt)->max();
    }

    private function inApplicationTimezone(CarbonInterface $instant): CarbonImmutable
    {
        return $instant->toImmutable()->setTimezone((string) config('app.timezone'));
    }
}
