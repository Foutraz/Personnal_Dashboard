<?php

namespace Functional\Todo\Console;

use Functional\Todo\Enums\TaskStatus;
use Functional\Todo\Models\TaskReminder;
use Functional\Todo\Notifications\TaskReminderNotification;
use Illuminate\Console\Command;

class SendDueTaskReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'todo:send-due-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify users of their tasks whose reminder time has arrived.';

    /**
     * Send the pending reminders that have reached their reminder time.
     */
    public function handle(): int
    {
        $reminders = TaskReminder::query()
            ->where('sent', false)
            ->where('remind_at', '<=', now())
            ->with('task.user')
            ->cursor();

        foreach ($reminders as $reminder) {
            $task = $reminder->task;

            if ($task === null || $task->status === TaskStatus::Done) {
                $reminder->update(['sent' => true]);

                continue;
            }

            $this->line("Sending reminder for task [{$task->id}].");

            $task->user?->notify(new TaskReminderNotification($task));

            $reminder->update(['sent' => true]);
        }

        $this->comment('Due task reminders processed.');

        return self::SUCCESS;
    }
}
