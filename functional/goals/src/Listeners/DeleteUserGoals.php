<?php

namespace Functional\Goals\Listeners;

use Functional\Goals\Models\Goal;
use Functional\Users\Events\UserDeleting;

class DeleteUserGoals
{
    /**
     * Delete the goals owned by the deleting user.
     */
    public function handle(UserDeleting $event): void
    {
        Goal::query()
            ->where('user_id', $event->user->id)
            ->cursor()
            ->each(fn (Goal $goal) => $goal->delete());
    }
}
