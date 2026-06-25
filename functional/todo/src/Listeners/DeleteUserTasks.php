<?php

namespace Functional\Todo\Listeners;

use Functional\Todo\Models\Task;
use Functional\Users\Events\UserDeleting;

class DeleteUserTasks
{
    /**
     * Delete the tasks owned by the deleting user.
     */
    public function handle(UserDeleting $event): void
    {
        Task::query()
            ->where('user_id', $event->user->id)
            ->cursor()
            ->each(fn (Task $task) => $task->delete());
    }
}
