<?php

namespace Functional\RecurringExpenses\Listeners;

use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\Users\Events\UserDeleting;

class DeleteUserRecurringExpenses
{
    /**
     * Delete the recurring expenses owned by the deleting user.
     */
    public function handle(UserDeleting $event): void
    {
        RecurringExpense::query()
            ->where('user_id', $event->user->id)
            ->cursor()
            ->each(fn (RecurringExpense $expense) => $expense->delete());
    }
}
