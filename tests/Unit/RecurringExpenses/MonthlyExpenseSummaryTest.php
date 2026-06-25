<?php

namespace Tests\Unit\RecurringExpenses;

use Functional\RecurringExpenses\Enums\ExpenseCategory;
use Functional\RecurringExpenses\Enums\ExpenseFrequency;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\RecurringExpenses\Services\MonthlyExpenseSummary;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MonthlyExpenseSummaryTest extends TestCase
{
    private MonthlyExpenseSummary $summary;

    /**
     * Prepare a fresh summary builder for each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->summary = new MonthlyExpenseSummary;
    }

    /**
     * Build an unsaved recurring expense with the given attributes.
     */
    private function expense(float $amount, ExpenseFrequency $frequency, ExpenseCategory $category): RecurringExpense
    {
        return new RecurringExpense([
            'amount' => $amount,
            'frequency' => $frequency,
            'category' => $category,
        ]);
    }

    #[Test]
    public function it_normalizes_a_yearly_expense_to_its_monthly_cost(): void
    {
        $cost = $this->summary->monthlyCost($this->expense(1200, ExpenseFrequency::Yearly, ExpenseCategory::Insurance));

        $this->assertSame(100.0, $cost);
    }

    #[Test]
    public function it_normalizes_a_quarterly_expense_to_its_monthly_cost(): void
    {
        $cost = $this->summary->monthlyCost($this->expense(300, ExpenseFrequency::Quarterly, ExpenseCategory::Other));

        $this->assertSame(100.0, $cost);
    }

    #[Test]
    public function it_normalizes_a_weekly_expense_to_its_monthly_cost(): void
    {
        $cost = $this->summary->monthlyCost($this->expense(12, ExpenseFrequency::Weekly, ExpenseCategory::Subscription));

        $this->assertSame(52.0, $cost);
    }

    #[Test]
    public function it_builds_the_total_and_per_category_breakdown(): void
    {
        $expenses = new Collection([
            $this->expense(950, ExpenseFrequency::Monthly, ExpenseCategory::Rent),
            $this->expense(1200, ExpenseFrequency::Yearly, ExpenseCategory::Insurance),
            $this->expense(15, ExpenseFrequency::Monthly, ExpenseCategory::Subscription),
        ]);

        $result = $this->summary->build($expenses);

        $this->assertSame(1065.0, $result->monthlyTotal);
        $this->assertSame(12780.0, $result->yearlyTotal);
        $this->assertSame(950.0, $result->perCategory[ExpenseCategory::Rent->value]);
        $this->assertSame(100.0, $result->perCategory[ExpenseCategory::Insurance->value]);
        $this->assertSame(15.0, $result->perCategory[ExpenseCategory::Subscription->value]);
        $this->assertSame(0.0, $result->perCategory[ExpenseCategory::Credit->value]);
    }
}
