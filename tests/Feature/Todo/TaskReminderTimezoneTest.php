<?php

namespace Tests\Feature\Todo;

use Carbon\CarbonImmutable;
use Functional\Todo\Models\Task;
use Functional\Todo\Notifications\TaskReminderNotification;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaskReminderTimezoneTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_states_the_due_date_of_the_reminder_in_the_display_timezone(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'user_id' => $user->id,
            'due_at' => CarbonImmutable::parse('2026-10-04 21:30:00', 'UTC'),
        ]);

        $mail = (new TaskReminderNotification($task))->toMail($user);

        $this->assertStringContainsString('04/10/2026 23:30', implode(' ', $mail->introLines));
        $this->assertStringNotContainsString('04/10/2026 21:30', implode(' ', $mail->introLines));
    }

    #[Test]
    public function it_keeps_the_stored_instant_in_the_database_payload(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'user_id' => $user->id,
            'due_at' => CarbonImmutable::parse('2026-10-04 21:30:00', 'UTC'),
        ]);

        $payload = (new TaskReminderNotification($task))->toArray($user);

        $this->assertSame('2026-10-04T21:30:00+00:00', $payload['due_at']);
    }

    #[Test]
    public function it_asks_for_attention_when_the_task_has_no_due_date(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'due_at' => null]);

        $mail = (new TaskReminderNotification($task))->toMail($user);

        $this->assertStringContainsString('nécessite votre attention', implode(' ', $mail->introLines));
    }
}
