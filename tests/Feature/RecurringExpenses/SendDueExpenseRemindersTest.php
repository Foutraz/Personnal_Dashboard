<?php

namespace Tests\Feature\RecurringExpenses;

use Functional\RecurringExpenses\Console\SendDueExpenseReminders;
use Functional\RecurringExpenses\Enums\ExpenseFrequency;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\RecurringExpenses\Notifications\ExpenseDueReminderNotification;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendDueExpenseRemindersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_notifies_the_user_within_the_lead_window_and_records_the_reminder(): void
    {
        config(['recurring-expenses.reminders.lead_days' => 3]);
        Notification::fake();

        $user = User::factory()->create();
        $expense = RecurringExpense::factory()->dueInDays(2)->create([
            'user_id' => $user->id,
            'frequency' => ExpenseFrequency::Monthly,
        ]);

        $this->artisan(SendDueExpenseReminders::class)->assertSuccessful();

        Notification::assertSentTo($user, ExpenseDueReminderNotification::class);

        $this->assertDatabaseHas('expense_reminders', [
            'recurring_expense_id' => $expense->id,
            'notified' => true,
        ]);
    }

    #[Test]
    public function it_does_not_notify_for_an_expense_outside_the_lead_window(): void
    {
        config(['recurring-expenses.reminders.lead_days' => 3]);
        Notification::fake();

        $user = User::factory()->create();
        RecurringExpense::factory()->dueInDays(20)->create(['user_id' => $user->id]);

        $this->artisan(SendDueExpenseReminders::class)->assertSuccessful();

        Notification::assertNothingSent();
    }

    #[Test]
    public function it_does_not_re_notify_an_already_notified_due_date(): void
    {
        config(['recurring-expenses.reminders.lead_days' => 3]);
        Notification::fake();

        $user = User::factory()->create();
        RecurringExpense::factory()->dueInDays(1)->create([
            'user_id' => $user->id,
            'frequency' => ExpenseFrequency::Yearly,
        ]);

        $this->artisan(SendDueExpenseReminders::class)->assertSuccessful();
        Notification::assertSentToTimes($user, ExpenseDueReminderNotification::class, 1);

        $this->artisan(SendDueExpenseReminders::class)->assertSuccessful();
        Notification::assertSentToTimes($user, ExpenseDueReminderNotification::class, 1);
    }

    #[Test]
    public function it_advances_the_next_due_date_after_processing(): void
    {
        config(['recurring-expenses.reminders.lead_days' => 3]);
        Notification::fake();

        $user = User::factory()->create();
        $expense = RecurringExpense::factory()->create([
            'user_id' => $user->id,
            'frequency' => ExpenseFrequency::Monthly,
            'due_day' => 5,
            'next_due_at' => now()->subDays(2),
        ]);

        $this->artisan(SendDueExpenseReminders::class)->assertSuccessful();

        $expense->refresh();

        $this->assertTrue($expense->next_due_at->isFuture());
    }
}
