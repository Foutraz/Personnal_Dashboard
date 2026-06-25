<?php

namespace Functional\RecurringExpenses\Actions;

use Functional\RecurringExpenses\Models\RecurringExpense;
use Illuminate\Support\Facades\Auth;

class AssignExpenseOwner
{
    /**
     * Force the creating recurring expense to belong to the authenticated user when one exists.
     */
    public function handle(RecurringExpense $expense): void
    {
        if (Auth::hasUser()) {
            $expense->user_id = (string) Auth::id();
        }
    }
}
