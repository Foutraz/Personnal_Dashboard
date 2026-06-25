<?php

namespace Functional\Finance\Actions;

use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Exceptions\SellExceedsHoldingsException;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;

class RecalculatePositionHoldings
{
    /**
     * Recompute the quantity and average buy price of a position from its transactions.
     */
    public function handle(Position $position): void
    {
        $heldQuantity = 0.0;
        $costBasis = 0.0;

        $transactions = $position->transactions()
            ->orderBy('executed_at')
            ->get();

        foreach ($transactions as $transaction) {
            $this->applyTransaction($position, $transaction, $heldQuantity, $costBasis);
        }

        $averageBuyPrice = $heldQuantity > 0.0 ? $costBasis / $heldQuantity : 0.0;

        $position->forceFill([
            'quantity' => round($heldQuantity, 8),
            'average_buy_price' => round($averageBuyPrice, 8),
        ])->save();
    }

    /**
     * Apply a single transaction to the running quantity and cost basis.
     */
    private function applyTransaction(Position $position, InvestmentTransaction $transaction, float &$heldQuantity, float &$costBasis): void
    {
        $quantity = (float) $transaction->quantity;
        $price = (float) $transaction->unit_price;

        if ($transaction->type === TransactionType::Buy) {
            $heldQuantity += $quantity;
            $costBasis += $quantity * $price;

            return;
        }

        if ($quantity > $heldQuantity + 1e-8) {
            throw SellExceedsHoldingsException::forPosition($position->asset_symbol);
        }

        $averageCost = $heldQuantity > 0.0 ? $costBasis / $heldQuantity : 0.0;
        $costBasis -= $averageCost * $quantity;
        $heldQuantity -= $quantity;
    }
}
