<?php

namespace Functional\Goals\Services;

use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Finance\Services\CapitalCalculator;
use Functional\Finance\Services\PerformanceCalculator;
use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Exceptions\UnsupportedGoalMetricException;
use Functional\Goals\Models\Goal;
use Functional\Goals\Services\Dto\GoalProgress;
use Functional\Sport\Models\SportActivity;
use Functional\Sport\Services\SportStatisticsCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GoalProgressCalculator
{
    /**
     * Build the calculator with the sibling module services it aggregates from.
     */
    public function __construct(
        private SportStatisticsCalculator $sportStatistics,
        private PerformanceCalculator $performance,
        private CapitalCalculator $capital,
    ) {}

    /**
     * Compute the progress of a goal against its target over its tracking period.
     */
    public function progress(Goal $goal): GoalProgress
    {
        if (! in_array($goal->metric, $goal->type->metrics(), true)) {
            throw UnsupportedGoalMetricException::forTypeAndMetric($goal->type, $goal->metric);
        }

        $currentValue = $this->currentValue($goal);

        return GoalProgress::fromValues($currentValue, (float) $goal->target_value, $this->isOnTrack($goal, $currentValue));
    }

    /**
     * Compute the raw current value of a goal for its metric.
     */
    public function currentValue(Goal $goal): float
    {
        return match ($goal->metric) {
            GoalMetric::SportDistance => $this->sportStatistics->totalDistance($this->sportActivities($goal)) / 1000,
            GoalMetric::SportElevation => $this->sportStatistics->totalElevation($this->sportActivities($goal)),
            GoalMetric::SportActivityCount => (float) $this->sportActivities($goal)->count(),
            GoalMetric::SportMovingTime => $this->sportStatistics->totalMovingTime($this->sportActivities($goal)) / 3600,
            GoalMetric::FinanceInvestedCapital => $this->capital->netInvested($this->financeTransactions($goal)),
            GoalMetric::FinancePortfolioValue => $this->performance->globalPerformance($this->financePositions($goal))->currentValue,
            GoalMetric::Manual => (float) ($goal->manual_current_value ?? 0),
        };
    }

    /**
     * Load the user's sport activities scoped to the goal period.
     *
     * @return Collection<int, SportActivity>
     */
    private function sportActivities(Goal $goal)
    {
        return SportActivity::query()
            ->where('user_id', $goal->user_id)
            ->when($goal->starts_at, fn (Builder $query, Carbon $startsAt): Builder => $query->where('started_at', '>=', $startsAt))
            ->when($goal->deadline, fn (Builder $query, Carbon $deadline): Builder => $query->where('started_at', '<=', $deadline))
            ->get();
    }

    /**
     * Load the user's finance positions.
     *
     * @return Collection<int, Position>
     */
    private function financePositions(Goal $goal)
    {
        return Position::query()
            ->where('user_id', $goal->user_id)
            ->get();
    }

    /**
     * Load the user's finance transactions scoped to the goal period.
     *
     * @return Collection<int, InvestmentTransaction>
     */
    private function financeTransactions(Goal $goal)
    {
        return InvestmentTransaction::query()
            ->where('user_id', $goal->user_id)
            ->when($goal->starts_at, fn (Builder $query, Carbon $startsAt): Builder => $query->where('executed_at', '>=', $startsAt))
            ->when($goal->deadline, fn (Builder $query, Carbon $deadline): Builder => $query->where('executed_at', '<=', $deadline))
            ->get();
    }

    /**
     * Determine whether the goal pace is ahead of the elapsed share of its deadline.
     */
    private function isOnTrack(Goal $goal, float $currentValue): bool
    {
        $target = (float) $goal->target_value;

        if ($target <= 0.0 || $currentValue >= $target) {
            return true;
        }

        if ($goal->starts_at === null || $goal->deadline === null) {
            return true;
        }

        $totalSeconds = $goal->deadline->getTimestamp() - $goal->starts_at->getTimestamp();

        if ($totalSeconds <= 0) {
            return true;
        }

        $elapsedSeconds = now()->getTimestamp() - $goal->starts_at->getTimestamp();
        $elapsedShare = max(min($elapsedSeconds / $totalSeconds, 1.0), 0.0);

        return ($currentValue / $target) >= $elapsedShare;
    }
}
