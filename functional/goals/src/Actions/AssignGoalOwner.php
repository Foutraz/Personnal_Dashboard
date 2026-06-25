<?php

namespace Functional\Goals\Actions;

use Functional\Goals\Models\Goal;
use Illuminate\Support\Facades\Auth;

class AssignGoalOwner
{
    /**
     * Force the creating goal to belong to the authenticated user when one exists.
     */
    public function handle(Goal $goal): void
    {
        if (Auth::hasUser()) {
            $goal->user_id = (string) Auth::id();
        }
    }
}
