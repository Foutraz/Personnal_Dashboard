<?php

namespace Functional\Finance\Services;

use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Services\Dto\CapitalBreakdown;
use Illuminate\Support\Collection;

class CapitalCalculator
{
    /**
     * Compute the capital breakdown of a single set of transactions.
     *
     * @param  Collection<int, InvestmentTransaction>  $transactions
     */
    public function breakdown(Collection $transactions): CapitalBreakdown
    {
        $invested = 0.0;
        $sold = 0.0;

        foreach ($transactions as $transaction) {
            $amount = (float) $transaction->quantity * (float) $transaction->unit_price;

            if ($transaction->type === TransactionType::Buy) {
                $invested += $amount;

                continue;
            }

            $sold += $amount;
        }

        return CapitalBreakdown::fromTotals($invested, $sold);
    }

    /**
     * Compute the aggregated capital breakdown across many positions.
     *
     * @param  Collection<int, InvestmentTransaction>  $transactions
     */
    public function globalBreakdown(Collection $transactions): CapitalBreakdown
    {
        return $this->breakdown($transactions);
    }

    /**
     * Compute the net invested capital of a set of transactions.
     *
     * @param  Collection<int, InvestmentTransaction>  $transactions
     */
    public function netInvested(Collection $transactions): float
    {
        return $this->breakdown($transactions)->netInvested;
    }

    /**
     * Compute the realized gain using an average-cost basis over chronological transactions.
     *
     * @param  Collection<int, InvestmentTransaction>  $transactions
     */
    public function realizedGain(Collection $transactions): float
    {
        $heldQuantity = 0.0;
        $costBasis = 0.0;
        $realized = 0.0;

        $ordered = $transactions->sortBy(fn (InvestmentTransaction $transaction): int => $transaction->executed_at->getTimestamp());

        foreach ($ordered as $transaction) {
            $quantity = (float) $transaction->quantity;
            $price = (float) $transaction->unit_price;

            if ($transaction->type === TransactionType::Buy) {
                $heldQuantity += $quantity;
                $costBasis += $quantity * $price;

                continue;
            }

            $averageCost = $heldQuantity > 0.0 ? $costBasis / $heldQuantity : 0.0;
            $realized += ($price - $averageCost) * $quantity;
            $costBasis -= $averageCost * $quantity;
            $heldQuantity -= $quantity;
        }

        return round($realized, 2);
    }
}
