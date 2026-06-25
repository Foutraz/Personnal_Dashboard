<?php

namespace Functional\RecurringExpenses\Console;

use Functional\RecurringExpenses\Actions\AdvanceDueExpenses;
use Functional\RecurringExpenses\Models\ExpenseReminder;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\RecurringExpenses\Notifications\ExpenseDueReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendDueExpenseReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recurring-expenses:send-due-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify users of their recurring expenses due within the configured lead window.';

    /**
     * Notify users of expenses due within the lead window then advance passed due dates.
     */
    public function handle(AdvanceDueExpenses $advancer): int
    {
        $leadDays = (int) config('recurring-expenses.reminders.lead_days', 3);
        $threshold = Carbon::now()->addDays($leadDays)->endOfDay();

        $expenses = RecurringExpense::query()
            ->where('active', true)
            ->where('next_due_at', '<=', $threshold)
            ->with('user')
            ->cursor();

        foreach ($expenses as $expense) {
            $this->line("Processing expense [{$expense->id}].");

            $this->notify($expense);

            $advancer->advance($expense);
        }

        $this->comment('Due expense reminders processed.');

        return self::SUCCESS;
    }

    /**
     * Record and dispatch the reminder for the expense unless it was already notified.
     */
    private function notify(RecurringExpense $expense): void
    {
        $reminder = ExpenseReminder::query()->firstOrCreate(
            [
                'recurring_expense_id' => $expense->id,
                'due_at' => $expense->next_due_at,
            ],
            ['notified' => false],
        );

        if ($reminder->notified) {
            return;
        }

        $expense->user?->notify(new ExpenseDueReminderNotification($expense));

        $reminder->update(['notified' => true]);
    }
}
