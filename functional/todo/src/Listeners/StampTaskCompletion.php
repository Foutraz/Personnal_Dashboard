<?php

namespace Functional\Todo\Listeners;

use Functional\Todo\Enums\TaskStatus;
use Functional\Todo\Models\Task;

class StampTaskCompletion
{
    public function handle(Task $task): void
    {
        if ($task->status !== TaskStatus::Done) {
            $task->completed_at = null;

            return;
        }

        $task->completed_at ??= now();
    }
}
