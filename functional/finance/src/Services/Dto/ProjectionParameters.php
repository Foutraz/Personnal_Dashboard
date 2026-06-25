<?php

namespace Functional\Finance\Services\Dto;

use Functional\Finance\Exceptions\InvalidSimulationParametersException;

readonly class ProjectionParameters
{
    /**
     * Hold the validated inputs of a portfolio projection.
     */
    public function __construct(
        public float $startingValue,
        public float $monthlyContribution,
        public int $years,
        public float $annualReturnRate,
    ) {}

    /**
     * Build validated parameters guarding against invalid inputs.
     */
    public static function create(float $startingValue, float $monthlyContribution, int $years, float $annualReturnRate): self
    {
        if ($years <= 0) {
            throw InvalidSimulationParametersException::nonPositiveDuration();
        }

        if ($monthlyContribution < 0.0) {
            throw InvalidSimulationParametersException::negativeAmount();
        }

        return new self($startingValue, $monthlyContribution, $years, $annualReturnRate);
    }
}
