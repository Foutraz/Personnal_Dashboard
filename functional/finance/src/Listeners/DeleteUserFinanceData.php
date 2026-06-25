<?php

namespace Functional\Finance\Listeners;

use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Users\Events\UserDeleting;

class DeleteUserFinanceData
{
    /**
     * Delete the positions and transactions owned by the deleting user.
     */
    public function handle(UserDeleting $event): void
    {
        InvestmentTransaction::query()
            ->where('user_id', $event->user->id)
            ->cursor()
            ->each(fn (InvestmentTransaction $transaction) => $transaction->delete());

        Position::query()
            ->where('user_id', $event->user->id)
            ->cursor()
            ->each(fn (Position $position) => $position->delete());
    }
}
