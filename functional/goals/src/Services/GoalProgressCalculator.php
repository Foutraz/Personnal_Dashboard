<?php

namespace Functional\Goals\Services;

use Carbon\CarbonInterface;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Finance\Services\CapitalCalculator;
use Functional\Finance\Services\PerformanceCalculator;
use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Exceptions\UnboundedGoalMetricException;
use Functional\Goals\Exceptions\UnsupportedGoalMetricException;
use Functional\Goals\Models\Goal;
use Functional\Goals\Services\Dto\GoalProgress;
use Functional\Todo\Models\Task;
use Functional\Todo\Services\TaskCompletionCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GoalProgressCalculator
{
    public function __construct(
        private GoalMetricAggregator $aggregator,
        private PerformanceCalculator $performance,
        private CapitalCalculator $capital,
        private TaskCompletionCalculator $taskCompletion,
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
            GoalMetric::FinancePortfolioValue => $this->performance->globalPerformance($this->financePositions($goal->user_id))->currentValue,
            GoalMetric::Manual => (float) ($goal->manual_current_value ?? 0),
            GoalMetric::TodoCompletionRate => $this->taskCompletion->completionRate($this->tasks($goal->user_id)),
            GoalMetric::SportDistance,
            GoalMetric::SportElevation,
            GoalMetric::SportActivityCount,
            GoalMetric::SportMovingTime,
            GoalMetric::FinanceInvestedCapital,
            GoalMetric::MotoDistance,
            GoalMetric::MotoRideCount,
            GoalMetric::ExplorationCells => $this->measure($goal->metric, $goal->user_id, $goal->starts_at, $goal->deadline),
        };
    }

    /**
     * @throws UnboundedGoalMetricException
     */
    public function measure(GoalMetric $metric, string $userId, ?CarbonInterface $from, ?CarbonInterface $until): float
    {
        return match ($metric) {
            GoalMetric::SportDistance,
            GoalMetric::SportElevation,
            GoalMetric::SportActivityCount,
            GoalMetric::SportMovingTime,
            GoalMetric::MotoDistance,
            GoalMetric::MotoRideCount,
            GoalMetric::ExplorationCells => $this->aggregator->total($metric, $userId, $from, $until),
            GoalMetric::FinanceInvestedCapital => $this->capital->netInvested($this->financeTransactions($userId, $from, $until)),
            GoalMetric::FinancePortfolioValue, GoalMetric::Manual, GoalMetric::TodoCompletionRate => throw new UnboundedGoalMetricException($metric),
        };
    }

    /**
     * @return Collection<int, Position>
     */
    private function financePositions(string $userId): Collection
    {
        return Position::query()
            ->where('user_id', $userId)
            ->get();
    }

    /**
     * @return Collection<int, InvestmentTransaction>
     */
    private function financeTransactions(string $userId, ?CarbonInterface $from, ?CarbonInterface $until): Collection
    {
        return InvestmentTransaction::query()
            ->where('user_id', $userId)
            ->when($from, fn (Builder $query, CarbonInterface $periodStart): Builder => $query->where('executed_at', '>=', $periodStart))
            ->when($until, fn (Builder $query, CarbonInterface $periodEnd): Builder => $query->where('executed_at', '<=', $periodEnd))
            ->get();
    }

    /**
     * @return Collection<int, Task>
     */
    private function tasks(string $userId): Collection
    {
        return Task::query()->where('user_id', $userId)->get();
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
