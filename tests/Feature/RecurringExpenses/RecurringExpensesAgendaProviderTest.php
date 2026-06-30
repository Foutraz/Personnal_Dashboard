<?php

namespace Tests\Feature\RecurringExpenses;

use Carbon\CarbonPeriod;
use Functional\RecurringExpenses\Dashboard\RecurringExpensesAgendaProvider;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringExpensesAgendaProviderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_due_expenses_within_the_period(): void
    {
        $user = User::factory()->create();
        RecurringExpense::factory()->create(['user_id' => $user->id, 'active' => true, 'next_due_at' => Carbon::parse('2026-07-05')]);
        RecurringExpense::factory()->create(['user_id' => $user->id, 'active' => true, 'next_due_at' => Carbon::parse('2026-12-01')]);
        RecurringExpense::factory()->create(['user_id' => User::factory()->create()->id, 'active' => true, 'next_due_at' => Carbon::parse('2026-07-06')]);

        $items = $this->app->make(RecurringExpensesAgendaProvider::class)->agendaItems($user, CarbonPeriod::create('2026-07-01', '2026-07-31'));

        $this->assertCount(1, $items);
        $this->assertSame('expense', $items->first()->source);
        $this->assertNotNull($items->first()->amount);
    }
}
