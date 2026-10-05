<?php

namespace Functional\Goals\Exceptions;

use Functional\Goals\Enums\GoalMetric;
use RuntimeException;

class UnaggregatableGoalMetricException extends RuntimeException
{
    public function __construct(public readonly GoalMetric $metric)
    {
        parent::__construct(sprintf('The metric "%s" is computed by its own module and cannot be aggregated in one query.', $metric->value));
    }
}
