<?php

namespace Tests\Feature\Todo;

use Functional\Todo\Enums\TaskPriority;
use Functional\Todo\Enums\TaskStatus;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaskValidationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_rejects_a_task_creation_missing_required_attributes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/tasks/mutate', [
            'mutate' => [
                ['operation' => 'create', 'attributes' => []],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'mutate.0.attributes.title',
            'mutate.0.attributes.priority',
            'mutate.0.attributes.status',
        ]);
    }

    #[Test]
    public function it_rejects_a_task_with_an_invalid_priority(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/tasks/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'title' => 'Broken task',
                        'priority' => 'banana',
                        'status' => TaskStatus::Pending->value,
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mutate.0.attributes.priority']);
    }

    #[Test]
    public function it_accepts_a_valid_task(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/tasks/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'title' => 'Valid task',
                        'priority' => TaskPriority::Medium->value,
                        'status' => TaskStatus::Pending->value,
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('tasks', ['title' => 'Valid task', 'user_id' => $user->id]);
    }

    private function createTaskWith(User $user, array $attributes): TestResponse
    {
        return $this->actingAs($user, 'api')->postJson('/api/tasks/mutate', [
            'mutate' => [[
                'operation' => 'create',
                'attributes' => [
                    'title' => 'Write the report',
                    'priority' => TaskPriority::Medium->value,
                    'status' => TaskStatus::Pending->value,
                    ...$attributes,
                ],
            ]],
        ]);
    }

    private function updateTaskWith(User $user, Task $task, array $attributes): TestResponse
    {
        return $this->actingAs($user, 'api')->postJson('/api/tasks/mutate', [
            'mutate' => [[
                'operation' => 'update',
                'key' => $task->id,
                'attributes' => $attributes,
            ]],
        ]);
    }

    public static function clientSuppliedServerManagedFields(): array
    {
        $cases = [];

        foreach (['completed_at', 'created_at', 'updated_at', 'id'] as $field) {
            $cases["{$field} filled"] = [$field, '2026-09-01 08:00:00'];
            $cases["{$field} null"] = [$field, null];
            $cases["{$field} empty string"] = [$field, ''];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('clientSuppliedServerManagedFields')]
    public function it_rejects_a_server_managed_field_sent_on_creation(string $field, ?string $suppliedValue): void
    {
        $user = User::factory()->create();

        $response = $this->createTaskWith($user, [$field => $suppliedValue]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(["mutate.0.attributes.{$field}"]);
        $this->assertSame(0, Task::query()->count());
    }

    #[Test]
    #[DataProvider('clientSuppliedServerManagedFields')]
    public function it_rejects_a_server_managed_field_sent_on_update(string $field, ?string $suppliedValue): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();
        $storedBefore = Task::query()->sole()->getAttributes();

        $response = $this->updateTaskWith($user, $task, [$field => $suppliedValue]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(["mutate.0.attributes.{$field}"]);
        $this->assertSame($storedBefore, Task::query()->sole()->getAttributes());
    }
}
