<?php

namespace Functional\Finance\Exceptions;

use RuntimeException;

class InvalidSimulationParametersException extends RuntimeException
{
    /**
     * Build the exception describing a non-positive duration in years.
     */
    public static function nonPositiveDuration(): self
    {
        return new self('The simulation duration must be a positive number of years.');
    }

    /**
     * Build the exception describing a negative contribution amount.
     */
    public static function negativeAmount(): self
    {
        return new self('The contribution amount must not be negative.');
    }
}
