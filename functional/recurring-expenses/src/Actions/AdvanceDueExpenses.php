<?php

namespace Functional\RecurringExpenses\Actions;

use Carbon\CarbonInterface;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\RecurringExpenses\Services\NextDueDateCalculator;
use Illuminate\Support\Carbon;

class AdvanceDueExpenses
{
    /**
     * Roll the next due date forward for any active expense whose due date has passed.
     */
    public function __construct(private NextDueDateCalculator $calculator) {}

    /**
     * Advance the given expense to its next future due date when its current one is in the past.
     */
    public function advance(RecurringExpense $expense, ?CarbonInterface $now = null): RecurringExpense
    {
        $reference = $now ?? Carbon::now();

        if ($expense->next_due_at->greaterThan($reference)) {
            return $expense;
        }

        $nextDueAt = $this->calculator->advance(
            $expense->frequency,
            $expense->next_due_at,
            $expense->due_day,
            $reference,
        );

        $expense->update(['next_due_at' => $nextDueAt]);

        return $expense;
    }
}
