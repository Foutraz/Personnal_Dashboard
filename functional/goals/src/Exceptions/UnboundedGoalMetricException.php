<?php

namespace Functional\Goals\Exceptions;

use Functional\Goals\Enums\GoalMetric;
use RuntimeException;

class UnboundedGoalMetricException extends RuntimeException
{
    public function __construct(public readonly GoalMetric $metric)
    {
        parent::__construct(sprintf('The metric "%s" is not bound to a period and cannot be measured over one.', $metric->value));
    }
}
