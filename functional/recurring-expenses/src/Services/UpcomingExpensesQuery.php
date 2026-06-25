<?php

namespace Functional\RecurringExpenses\Services;

use Functional\RecurringExpenses\Models\RecurringExpense;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class UpcomingExpensesQuery
{
    /**
     * Get the active recurring expenses of the user due within the given number of days.
     *
     * @return Collection<int, RecurringExpense>
     */
    public function forUser(string $userId, int $withinDays = 30): Collection
    {
        return RecurringExpense::query()
            ->where('user_id', $userId)
            ->where('active', true)
            ->whereBetween('next_due_at', [Carbon::now()->startOfDay(), Carbon::now()->addDays($withinDays)->endOfDay()])
            ->orderBy('next_due_at')
            ->get();
    }
}
