<?php

namespace Functional\Sport\Events;

use Illuminate\Foundation\Events\Dispatchable;

class StravaActivitiesSynced
{
    use Dispatchable;

    public function __construct(public string $userId) {}
}
