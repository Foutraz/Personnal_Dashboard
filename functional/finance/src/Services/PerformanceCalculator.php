<?php

namespace Functional\Finance\Services;

use Functional\Finance\Models\Position;
use Functional\Finance\Services\Dto\PerformanceResult;
use Illuminate\Support\Collection;

class PerformanceCalculator
{
    /**
     * Compute the current market value of a single position.
     */
    public function positionValue(Position $position): float
    {
        if (! $position->hasCurrentPrice()) {
            return 0.0;
        }

        return round((float) $position->quantity * (float) $position->current_price, 2);
    }

    /**
     * Compute the net invested capital of a single position from its average buy price.
     */
    public function positionNetInvested(Position $position): float
    {
        return round((float) $position->quantity * (float) $position->average_buy_price, 2);
    }

    /**
     * Compute the performance result of a single position.
     */
    public function positionPerformance(Position $position): PerformanceResult
    {
        return PerformanceResult::fromValues(
            $this->positionValue($position),
            $this->positionNetInvested($position),
        );
    }

    /**
     * Compute the aggregated performance result across many positions.
     *
     * @param  Collection<int, Position>  $positions
     */
    public function globalPerformance(Collection $positions): PerformanceResult
    {
        $currentValue = 0.0;
        $netInvested = 0.0;

        foreach ($positions as $position) {
            $currentValue += $this->positionValue($position);
            $netInvested += $this->positionNetInvested($position);
        }

        return PerformanceResult::fromValues($currentValue, $netInvested);
    }

    /**
     * Compute the relative weight of each position in the portfolio value.
     *
     * @param  Collection<int, Position>  $positions
     * @return array<string, float>
     */
    public function allocationWeights(Collection $positions): array
    {
        $totalValue = 0.0;

        foreach ($positions as $position) {
            $totalValue += $this->positionValue($position);
        }

        if ($totalValue <= 0.0) {
            return [];
        }

        $weights = [];

        foreach ($positions as $position) {
            $weights[$position->asset_symbol] = round(($this->positionValue($position) / $totalValue) * 100, 2);
        }

        return $weights;
    }
}
