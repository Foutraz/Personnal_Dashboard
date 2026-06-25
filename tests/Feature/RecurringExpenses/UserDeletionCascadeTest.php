<?php

namespace Tests\Feature\RecurringExpenses;

use Functional\RecurringExpenses\Models\ExpenseReminder;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserDeletionCascadeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_the_users_expenses_and_their_reminders_when_the_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $expense = RecurringExpense::factory()->create(['user_id' => $user->id]);
        $reminder = ExpenseReminder::factory()->create(['recurring_expense_id' => $expense->id]);

        $other = User::factory()->create();
        $otherExpense = RecurringExpense::factory()->create(['user_id' => $other->id]);

        $user->delete();

        $this->assertSoftDeleted('recurring_expenses', ['id' => $expense->id]);
        $this->assertDatabaseMissing('expense_reminders', ['id' => $reminder->id]);
        $this->assertDatabaseHas('recurring_expenses', ['id' => $otherExpense->id, 'deleted_at' => null]);
    }

    #[Test]
    public function it_deletes_the_reminders_when_the_expense_is_deleted(): void
    {
        $user = User::factory()->create();
        $expense = RecurringExpense::factory()->create(['user_id' => $user->id]);
        $reminder = ExpenseReminder::factory()->create(['recurring_expense_id' => $expense->id]);

        $expense->delete();

        $this->assertDatabaseMissing('expense_reminders', ['id' => $reminder->id]);
    }
}
