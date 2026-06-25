<?php

namespace Functional\Finance\Services\Dto;

readonly class CapitalBreakdown
{
    /**
     * Hold the invested, sold and net capital figures of a scope.
     */
    public function __construct(
        public float $investedAmount,
        public float $soldProceeds,
        public float $netInvested,
    ) {}

    /**
     * Build a breakdown from raw buy and sell totals.
     */
    public static function fromTotals(float $investedAmount, float $soldProceeds): self
    {
        return new self(
            round($investedAmount, 2),
            round($soldProceeds, 2),
            round($investedAmount - $soldProceeds, 2),
        );
    }

    /**
     * Expose the breakdown as a serializable array.
     *
     * @return array{invested_amount: float, sold_proceeds: float, net_invested: float}
     */
    public function toArray(): array
    {
        return [
            'invested_amount' => $this->investedAmount,
            'sold_proceeds' => $this->soldProceeds,
            'net_invested' => $this->netInvested,
        ];
    }
}
