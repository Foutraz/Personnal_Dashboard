<?php

namespace Functional\Goals\Actions;

use Functional\Goals\Enums\GoalStatus;
use Functional\Goals\Models\Goal;
use Functional\Goals\Services\Dto\GoalProgress;
use Functional\Goals\Services\GoalProgressCalculator;

class RefreshGoalStatus
{
    /**
     * Build the action with the progress calculator it relies on.
     */
    public function __construct(private GoalProgressCalculator $calculator) {}

    /**
     * Flip an active goal to achieved when its computed progress reaches the threshold.
     */
    public function handle(Goal $goal): GoalProgress
    {
        $progress = $this->calculator->progress($goal);

        $threshold = (float) config('goals.achievement.threshold', 100.0);

        if ($goal->status === GoalStatus::Active && $progress->percentage >= $threshold) {
            $goal->status = GoalStatus::Achieved;
            $goal->save();
        }

        return $progress;
    }
}
