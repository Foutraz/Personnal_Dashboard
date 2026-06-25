<?php

namespace Functional\Finance\Services\Dto;

use Functional\Finance\Enums\DcaFrequency;
use Functional\Finance\Exceptions\InvalidSimulationParametersException;

readonly class DcaParameters
{
    /**
     * Hold the validated inputs of a dollar-cost-averaging simulation.
     */
    public function __construct(
        public float $periodicAmount,
        public DcaFrequency $frequency,
        public int $years,
        public float $annualReturnRate,
    ) {}

    /**
     * Build validated parameters guarding against invalid inputs.
     */
    public static function create(float $periodicAmount, DcaFrequency $frequency, int $years, float $annualReturnRate): self
    {
        if ($years <= 0) {
            throw InvalidSimulationParametersException::nonPositiveDuration();
        }

        if ($periodicAmount < 0.0) {
            throw InvalidSimulationParametersException::negativeAmount();
        }

        return new self($periodicAmount, $frequency, $years, $annualReturnRate);
    }
}
