<?php

namespace Tests\Feature\Todo;

use Functional\Todo\Enums\TaskPriority;
use Functional\Todo\Enums\TaskStatus;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
