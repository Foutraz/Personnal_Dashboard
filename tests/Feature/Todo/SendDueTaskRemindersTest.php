<?php

namespace Tests\Feature\Todo;

use Functional\Todo\Console\SendDueTaskReminders;
use Functional\Todo\Models\Task;
use Functional\Todo\Models\TaskReminder;
use Functional\Todo\Notifications\TaskReminderNotification;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendDueTaskRemindersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_notifies_the_user_of_a_due_reminder(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $reminder = TaskReminder::factory()->create([
            'task_id' => $task->id,
            'remind_at' => now()->subMinute(),
            'sent' => false,
        ]);

        $this->artisan(SendDueTaskReminders::class)->assertSuccessful();

        Notification::assertSentTo($user, TaskReminderNotification::class);

        $this->assertDatabaseHas('task_reminders', [
            'id' => $reminder->id,
            'sent' => true,
        ]);
    }

    #[Test]
    public function it_does_not_resend_an_already_sent_reminder(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);
        TaskReminder::factory()->create([
            'task_id' => $task->id,
            'remind_at' => now()->subMinute(),
            'sent' => true,
        ]);

        $this->artisan(SendDueTaskReminders::class)->assertSuccessful();

        Notification::assertNothingSent();
    }

    #[Test]
    public function it_does_not_notify_for_a_future_reminder(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);
        TaskReminder::factory()->create([
            'task_id' => $task->id,
            'remind_at' => now()->addDay(),
            'sent' => false,
        ]);

        $this->artisan(SendDueTaskReminders::class)->assertSuccessful();

        Notification::assertNothingSent();
    }
}
