<?php

namespace Functional\Moto\Actions;

use Functional\Moto\Models\MotoRide;
use Illuminate\Support\Facades\Auth;

class AssignRideOwner
{
    /**
     * Force the creating moto ride to belong to the authenticated user when one exists.
     */
    public function handle(MotoRide $ride): void
    {
        if (Auth::hasUser()) {
            $ride->user_id = (string) Auth::id();
        }
    }
}
