<?php

namespace Tests\Feature\Todo;

use Functional\Todo\Enums\TaskPriority;
use Functional\Todo\Enums\TaskStatus;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TasksApiScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_only_returns_the_authenticated_users_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $ownTasks = Task::factory()->count(2)->create(['user_id' => $user->id]);
        Task::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/tasks/search', [
            'search' => [],
        ]);

        $response->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame($ownTasks->pluck('id')->sort()->values()->all(), $returnedIds);
    }

    #[Test]
    public function it_assigns_the_authenticated_user_when_creating_a_task(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/tasks/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'title' => 'New task',
                        'priority' => TaskPriority::High->value,
                        'status' => TaskStatus::Pending->value,
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('tasks', [
            'title' => 'New task',
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_forbids_deleting_another_users_task(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $task = Task::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'api')->deleteJson('/api/tasks', [
            'resources' => [$task->id],
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_forbids_updating_another_users_task(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $task = Task::factory()->create(['user_id' => $other->id, 'title' => 'Original']);

        $response = $this->actingAs($user, 'api')->postJson('/api/tasks/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $task->id,
                    'attributes' => ['title' => 'Hijacked'],
                ],
            ],
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Original',
        ]);
    }

    #[Test]
    public function it_allows_the_owner_to_update_and_delete_their_task(): void
    {
        $user = User::factory()->create();

        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Original']);

        $this->actingAs($user, 'api')->postJson('/api/tasks/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $task->id,
                    'attributes' => ['title' => 'Updated'],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated',
        ]);

        $this->actingAs($user, 'api')->deleteJson('/api/tasks', [
            'resources' => [$task->id],
        ])->assertOk();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }
}
