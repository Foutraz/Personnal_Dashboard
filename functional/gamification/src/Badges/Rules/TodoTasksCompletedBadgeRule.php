<?php

namespace Functional\Gamification\Badges\Rules;

use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\BadgeUnit;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;

class TodoTasksCompletedBadgeRule implements BadgeRule
{
    /**
     * Get the badge family key matching the configured thresholds.
     */
    public function key(): string
    {
        return 'todo_tasks_completed';
    }

    /**
     * Get the domain the badge family belongs to.
     */
    public function domain(): GamificationDomain
    {
        return GamificationDomain::Todo;
    }

    /**
     * Get the unit in which the rule expresses its measure.
     */
    public function unit(): BadgeUnit
    {
        return BadgeUnit::Count;
    }

    /**
     * Measure the user's progress with a single scoped aggregation.
     */
    public function measure(User $user): float
    {
        return (float) Task::query()->whereBelongsTo($user)->whereNotNull('completed_at')->count();
    }
}
