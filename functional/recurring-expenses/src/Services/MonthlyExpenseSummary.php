<?php

namespace Functional\RecurringExpenses\Services;

use Functional\RecurringExpenses\Enums\ExpenseCategory;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\RecurringExpenses\Services\Dto\MonthlySummary;
use Illuminate\Support\Collection;

class MonthlyExpenseSummary
{
    /**
     * Build the normalized monthly summary of the given recurring expenses.
     *
     * @param  Collection<int, RecurringExpense>  $expenses
     */
    public function build(Collection $expenses): MonthlySummary
    {
        $perCategory = [];

        foreach (ExpenseCategory::cases() as $category) {
            $perCategory[$category->value] = 0.0;
        }

        $monthlyTotal = 0.0;

        foreach ($expenses as $expense) {
            $monthlyCost = $this->monthlyCost($expense);
            $monthlyTotal += $monthlyCost;
            $perCategory[$expense->category->value] += $monthlyCost;
        }

        $perCategory = array_map(fn (float $value): float => round($value, 2), $perCategory);

        return new MonthlySummary(
            monthlyTotal: round($monthlyTotal, 2),
            yearlyTotal: round($monthlyTotal * 12, 2),
            perCategory: $perCategory,
        );
    }

    /**
     * Normalize a single recurring expense to its equivalent monthly cost.
     */
    public function monthlyCost(RecurringExpense $expense): float
    {
        $amount = (float) $expense->amount;

        return round($amount * $expense->frequency->occurrencesPerYear() / 12, 2);
    }
}
