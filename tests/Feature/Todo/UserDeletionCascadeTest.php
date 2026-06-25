<?php

namespace Tests\Feature\Todo;

use Functional\Todo\Models\Task;
use Functional\Todo\Models\TaskReminder;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserDeletionCascadeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_the_users_tasks_and_their_reminders_when_the_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $reminder = TaskReminder::factory()->create(['task_id' => $task->id]);

        $other = User::factory()->create();
        $otherTask = Task::factory()->create(['user_id' => $other->id]);

        $user->delete();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('task_reminders', ['id' => $reminder->id]);
        $this->assertDatabaseHas('tasks', ['id' => $otherTask->id, 'deleted_at' => null]);
    }
}
