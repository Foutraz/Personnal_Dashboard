<?php

namespace Functional\Finance\Services\Dto;

readonly class PerformanceResult
{
    /**
     * Hold the current value and gain figures of a scope.
     */
    public function __construct(
        public float $currentValue,
        public float $netInvested,
        public float $absoluteGain,
        public float $percentageGain,
    ) {}

    /**
     * Build a performance result from a current value and a net invested amount.
     */
    public static function fromValues(float $currentValue, float $netInvested): self
    {
        $absoluteGain = $currentValue - $netInvested;
        $percentageGain = $netInvested > 0.0 ? ($absoluteGain / $netInvested) * 100 : 0.0;

        return new self(
            round($currentValue, 2),
            round($netInvested, 2),
            round($absoluteGain, 2),
            round($percentageGain, 2),
        );
    }

    /**
     * Expose the result as a serializable array.
     *
     * @return array{current_value: float, net_invested: float, absolute_gain: float, percentage_gain: float}
     */
    public function toArray(): array
    {
        return [
            'current_value' => $this->currentValue,
            'net_invested' => $this->netInvested,
            'absolute_gain' => $this->absoluteGain,
            'percentage_gain' => $this->percentageGain,
        ];
    }
}
