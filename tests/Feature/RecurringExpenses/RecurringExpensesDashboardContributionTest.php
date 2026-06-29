<?php

namespace Tests\Feature\RecurringExpenses;

use Functional\RecurringExpenses\Dashboard\RecurringExpensesDashboardContribution;
use Functional\RecurringExpenses\Enums\ExpenseFrequency;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringExpensesDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sums_the_user_monthly_cost(): void
    {
        $user = User::factory()->create();
        RecurringExpense::factory()->count(2)->create([
            'user_id' => $user->id,
            'amount' => 30,
            'frequency' => ExpenseFrequency::Monthly,
            'active' => true,
        ]);
        RecurringExpense::factory()->create(['user_id' => User::factory()->create()->id, 'amount' => 999]);

        $summary = $this->app->make(RecurringExpensesDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('deadlines', $summary->key);
        $this->assertSame(60, $summary->order);
        $this->assertTrue($summary->available);
        $this->assertSame('60', $summary->metricValue);
        $this->assertSame('€/mois', $summary->metricUnit);
    }
}
