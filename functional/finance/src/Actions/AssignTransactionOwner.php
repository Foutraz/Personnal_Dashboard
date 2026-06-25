<?php

namespace Functional\Finance\Actions;

use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Illuminate\Support\Facades\Auth;

class AssignTransactionOwner
{
    /**
     * Force the creating transaction to belong to the owner of its parent position.
     */
    public function handle(InvestmentTransaction $transaction): void
    {
        $position = Position::query()->find($transaction->position_id);

        if ($position !== null) {
            $transaction->user_id = $position->user_id;

            return;
        }

        if (Auth::hasUser()) {
            $transaction->user_id = (string) Auth::id();
        }
    }
}
