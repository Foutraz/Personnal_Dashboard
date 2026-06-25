<?php

namespace Functional\Finance\Actions;

use Functional\Finance\Models\Position;
use Illuminate\Support\Facades\Auth;

class AssignPositionOwner
{
    /**
     * Force the creating position to belong to the authenticated user when one exists.
     */
    public function handle(Position $position): void
    {
        if (Auth::hasUser()) {
            $position->user_id = (string) Auth::id();
        }
    }
}
