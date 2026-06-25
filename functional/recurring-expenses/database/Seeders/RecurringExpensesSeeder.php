<?php

namespace Functional\RecurringExpenses\Database\Seeders;

use Functional\RecurringExpenses\Enums\ExpenseCategory;
use Functional\RecurringExpenses\Enums\ExpenseFrequency;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Illuminate\Database\Seeder;

class RecurringExpensesSeeder extends Seeder
{
    /**
     * Seed a handful of recurring expenses.
     */
    public function run(): void
    {
        RecurringExpense::factory()->count(6)->create();

        RecurringExpense::factory()->create([
            'label' => 'Loyer appartement',
            'amount' => 950.00,
            'category' => ExpenseCategory::Rent,
            'frequency' => ExpenseFrequency::Monthly,
            'due_day' => 5,
        ]);
    }
}
