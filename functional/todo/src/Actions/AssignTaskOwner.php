<?php

namespace Functional\Todo\Actions;

use Functional\Todo\Models\Task;
use Illuminate\Support\Facades\Auth;

class AssignTaskOwner
{
    /**
     * Force the creating task to belong to the authenticated user when one exists.
     */
    public function handle(Task $task): void
    {
        if (Auth::hasUser()) {
            $task->user_id = (string) Auth::id();
        }
    }
}
