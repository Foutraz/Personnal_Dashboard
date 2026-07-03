<?php

namespace Functional\Exploration\Events;

use Illuminate\Foundation\Events\Dispatchable;

class CoverageRebuilt
{
    use Dispatchable;

    public function __construct(public string $userId) {}
}
