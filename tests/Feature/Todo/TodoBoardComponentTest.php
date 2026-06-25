<?php

namespace Tests\Feature\Todo;

use Functional\Todo\Enums\TaskPriority;
use Functional\Todo\Enums\TaskStatus;
use Functional\Todo\Livewire\TodoBoard;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TodoBoardComponentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_task_owned_by_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(TodoBoard::class)
            ->set('newTitle', 'Acheter du café')
            ->set('newPriority', TaskPriority::High->value)
            ->call('createTask')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Acheter du café',
            'user_id' => $user->id,
            'priority' => TaskPriority::High->value,
            'status' => TaskStatus::Pending->value,
        ]);
    }

    #[Test]
    public function it_validates_the_title_is_required(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(TodoBoard::class)
            ->set('newTitle', '')
            ->call('createTask')
            ->assertHasErrors(['newTitle' => 'required']);
    }

    #[Test]
    public function it_completes_a_task_setting_the_completed_timestamp(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Pending]);

        Livewire::actingAs($user, 'web')
            ->test(TodoBoard::class)
            ->call('toggleComplete', $task->id);

        $task->refresh();

        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertNotNull($task->completed_at);
    }

    #[Test]
    public function it_filters_tasks_by_status(): void
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'title' => 'Tâche en attente', 'status' => TaskStatus::Pending]);
        Task::factory()->completed()->create(['user_id' => $user->id, 'title' => 'Tâche terminée']);

        Livewire::actingAs($user, 'web')
            ->test(TodoBoard::class)
            ->set('statusFilter', TaskStatus::Done->value)
            ->assertSee('Tâche terminée')
            ->assertDontSee('Tâche en attente');
    }

    #[Test]
    public function it_deletes_a_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user, 'web')
            ->test(TodoBoard::class)
            ->call('deleteTask', $task->id);

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    #[Test]
    public function it_does_not_delete_another_users_task(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $other->id]);

        Livewire::actingAs($user, 'web')
            ->test(TodoBoard::class)
            ->call('deleteTask', $task->id);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'deleted_at' => null]);
    }
}
