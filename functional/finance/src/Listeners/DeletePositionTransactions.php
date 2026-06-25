<?php

namespace Functional\Finance\Listeners;

use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;

class DeletePositionTransactions
{
    /**
     * Delete the transactions owned by the deleting position.
     */
    public function handle(Position $position): void
    {
        InvestmentTransaction::query()
            ->where('position_id', $position->id)
            ->cursor()
            ->each(fn (InvestmentTransaction $transaction) => $transaction->delete());
    }
}
