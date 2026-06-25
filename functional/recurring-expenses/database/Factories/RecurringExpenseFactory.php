<?php

namespace Functional\RecurringExpenses\Database\Factories;

use Functional\RecurringExpenses\Enums\ExpenseCategory;
use Functional\RecurringExpenses\Enums\ExpenseFrequency;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringExpense>
 */
class RecurringExpenseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<RecurringExpense>
     */
    protected $model = RecurringExpense::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $frequency = faker()->randomElement(ExpenseFrequency::cases());

        return [
            'user_id' => User::factory(),
            'label' => faker()->words(2),
            'amount' => faker()->float(2, 10, 1500),
            'currency' => 'EUR',
            'category' => faker()->randomElement(ExpenseCategory::cases()),
            'frequency' => $frequency,
            'due_day' => $frequency === ExpenseFrequency::Monthly ? faker()->number(1, 28) : null,
            'starts_at' => now()->subMonths(faker()->number(1, 12)),
            'ends_at' => null,
            'next_due_at' => now()->addDays(faker()->number(1, 40)),
            'active' => true,
            'note' => faker()->boolean() ? faker()->words(6) : null,
        ];
    }

    /**
     * Indicate that the recurring expense is due within the given number of days.
     */
    public function dueInDays(int $days): static
    {
        return $this->state(fn (): array => [
            'next_due_at' => now()->addDays($days),
        ]);
    }

    /**
     * Indicate that the recurring expense is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'active' => false,
        ]);
    }
}
