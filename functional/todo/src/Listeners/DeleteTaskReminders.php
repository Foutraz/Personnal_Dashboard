<?php

namespace Functional\Todo\Listeners;

use Functional\Todo\Models\Task;
use Functional\Todo\Models\TaskReminder;

class DeleteTaskReminders
{
    /**
     * Delete the reminders owned by the deleting task.
     */
    public function handle(Task $task): void
    {
        TaskReminder::query()
            ->where('task_id', $task->id)
            ->cursor()
            ->each(fn (TaskReminder $reminder) => $reminder->delete());
    }
}
