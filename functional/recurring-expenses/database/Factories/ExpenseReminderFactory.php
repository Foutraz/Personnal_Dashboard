<?php

namespace Functional\RecurringExpenses\Database\Factories;

use Functional\RecurringExpenses\Models\ExpenseReminder;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseReminder>
 */
class ExpenseReminderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ExpenseReminder>
     */
    protected $model = ExpenseReminder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recurring_expense_id' => RecurringExpense::factory(),
            'due_at' => now()->addDays(faker()->number(1, 10)),
            'notified' => false,
        ];
    }
}
