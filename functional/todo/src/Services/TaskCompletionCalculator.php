<?php

namespace Functional\Todo\Services;

use Functional\Todo\Enums\TaskPriority;
use Functional\Todo\Enums\TaskStatus;
use Functional\Todo\Models\Task;
use Illuminate\Support\Collection;

class TaskCompletionCalculator
{
    /**
     * Count the tasks grouped by their status.
     *
     * @param  Collection<int, Task>  $tasks
     * @return array<string, int>
     */
    public function countByStatus(Collection $tasks): array
    {
        $counts = [];

        foreach (TaskStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        foreach ($tasks as $task) {
            $counts[$task->status->value]++;
        }

        return $counts;
    }

    /**
     * Count the tasks grouped by their priority.
     *
     * @param  Collection<int, Task>  $tasks
     * @return array<string, int>
     */
    public function countByPriority(Collection $tasks): array
    {
        $counts = [];

        foreach (TaskPriority::cases() as $priority) {
            $counts[$priority->value] = 0;
        }

        foreach ($tasks as $task) {
            $counts[$task->priority->value]++;
        }

        return $counts;
    }

    /**
     * Compute the completion rate of the tasks as a percentage.
     *
     * @param  Collection<int, Task>  $tasks
     */
    public function completionRate(Collection $tasks): float
    {
        $total = $tasks->count();

        if ($total === 0) {
            return 0.0;
        }

        $done = $tasks->filter(fn (Task $task): bool => $task->status === TaskStatus::Done)->count();

        return round(($done / $total) * 100, 1);
    }

    /**
     * Aggregate the count of completed tasks bucketed by the given date format.
     *
     * @param  Collection<int, Task>  $tasks
     * @return array<string, int>
     */
    public function completedByPeriod(Collection $tasks, string $format): array
    {
        return $tasks
            ->filter(fn (Task $task): bool => $task->status === TaskStatus::Done && $task->completed_at !== null)
            ->sortBy(fn (Task $task): int => $task->completed_at->getTimestamp())
            ->groupBy(fn (Task $task): string => $task->completed_at->format($format))
            ->map(fn (Collection $group): int => $group->count())
            ->all();
    }
}
