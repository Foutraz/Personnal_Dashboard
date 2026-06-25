<?php

namespace Functional\Goals\Exceptions;

use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Enums\GoalType;
use RuntimeException;

class UnsupportedGoalMetricException extends RuntimeException
{
    /**
     * Build the exception for a metric that does not belong to the goal type.
     */
    public static function forTypeAndMetric(GoalType $type, GoalMetric $metric): self
    {
        return new self(sprintf('The metric "%s" is not supported for the goal type "%s".', $metric->value, $type->value));
    }
}
