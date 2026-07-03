<?php

namespace Functional\Health\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WithingsMeasurementsSynced
{
    use Dispatchable;

    public function __construct(public string $userId) {}
}
