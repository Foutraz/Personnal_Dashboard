<?php

namespace Functional\RecurringExpenses\Listeners;

use Functional\RecurringExpenses\Models\ExpenseReminder;
use Functional\RecurringExpenses\Models\RecurringExpense;

class DeleteExpenseReminders
{
    /**
     * Delete the reminders owned by the deleting recurring expense.
     */
    public function handle(RecurringExpense $expense): void
    {
        ExpenseReminder::query()
            ->where('recurring_expense_id', $expense->id)
            ->cursor()
            ->each(fn (ExpenseReminder $reminder) => $reminder->delete());
    }
}
