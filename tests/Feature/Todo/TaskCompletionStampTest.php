<?php

namespace Tests\Feature\Todo;

use Functional\Todo\Enums\TaskPriority;
use Functional\Todo\Enums\TaskStatus;
use Functional\Todo\Listeners\StampTaskCompletion;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaskCompletionStampTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
        $this->owner = User::factory()->create();
    }

    private function createTask(TaskStatus $status): TestResponse
    {
        return $this->actingAs($this->owner, 'api')->postJson('/api/tasks/mutate', [
            'mutate' => [[
                'operation' => 'create',
                'attributes' => [
                    'title' => 'Write the report',
                    'priority' => TaskPriority::Medium->value,
                    'status' => $status->value,
                ],
            ]],
        ]);
    }

    private function updateTask(Task $task, array $attributes): TestResponse
    {
        return $this->actingAs($this->owner, 'api')->postJson('/api/tasks/mutate', [
            'mutate' => [[
                'operation' => 'update',
                'key' => $task->id,
                'attributes' => $attributes,
            ]],
        ]);
    }

    #[Test]
    public function it_stamps_the_server_instant_on_a_task_created_as_done(): void
    {
        $this->createTask(TaskStatus::Done)->assertOk();

        $task = Task::query()->sole();
        $this->assertTrue($task->completed_at->equalTo(Carbon::parse('2026-10-01 10:00:00', 'UTC')));
    }

    #[Test]
    public function it_leaves_the_completion_empty_on_a_task_created_as_pending(): void
    {
        $this->createTask(TaskStatus::Pending)->assertOk();

        $this->assertNull(Task::query()->sole()->completed_at);
    }

    #[Test]
    public function it_stamps_the_server_instant_when_a_pending_task_is_marked_done(): void
    {
        $task = Task::factory()->for($this->owner)->create(['status' => TaskStatus::Pending]);
        $this->travelTo(Carbon::parse('2026-10-02 08:00:00', 'UTC'));

        $this->updateTask($task, ['status' => TaskStatus::Done->value])->assertOk();

        $this->assertTrue($task->fresh()->completed_at->equalTo(Carbon::parse('2026-10-02 08:00:00', 'UTC')));
    }

    #[Test]
    public function it_keeps_the_completion_instant_when_a_done_task_is_renamed(): void
    {
        $task = Task::factory()->completed()->for($this->owner)->create([
            'completed_at' => Carbon::parse('2026-10-02 08:00:00', 'UTC'),
        ]);
        $this->travelTo(Carbon::parse('2026-10-03 09:00:00', 'UTC'));

        $this->updateTask($task, ['title' => 'Renamed'])->assertOk();

        $this->assertTrue($task->fresh()->completed_at->equalTo(Carbon::parse('2026-10-02 08:00:00', 'UTC')));
    }

    #[Test]
    public function it_clears_the_completion_instant_when_a_done_task_goes_back_to_pending(): void
    {
        $task = Task::factory()->completed()->for($this->owner)->create();

        $this->updateTask($task, ['status' => TaskStatus::Pending->value])->assertOk();

        $this->assertNull($task->fresh()->completed_at);
    }

    #[Test]
    public function it_keeps_a_completion_instant_set_on_the_server_for_a_done_task(): void
    {
        $task = Task::factory()->completed()->create([
            'completed_at' => Carbon::parse('2026-09-20 18:00:00', 'UTC'),
        ]);

        $this->assertTrue($task->fresh()->completed_at->equalTo(Carbon::parse('2026-09-20 18:00:00', 'UTC')));
    }

    #[Test]
    public function it_clears_a_completion_instant_set_on_the_server_for_a_pending_task(): void
    {
        $task = Task::factory()->create([
            'completed_at' => Carbon::parse('2026-09-20 18:00:00', 'UTC'),
        ]);

        $this->assertNull($task->fresh()->completed_at);
    }

    #[Test]
    public function it_clears_a_completion_instant_set_on_the_server_for_an_in_progress_task(): void
    {
        $task = Task::factory()->create([
            'status' => TaskStatus::InProgress,
            'completed_at' => Carbon::parse('2026-09-20 18:00:00', 'UTC'),
        ]);

        $this->assertNull($task->fresh()->completed_at);
    }

    #[Test]
    public function it_stamps_the_server_instant_on_a_done_task_created_without_completion(): void
    {
        $task = Task::factory()->create(['status' => TaskStatus::Done]);

        $this->assertTrue($task->fresh()->completed_at->equalTo(Carbon::parse('2026-10-01 10:00:00', 'UTC')));
    }

    #[Test]
    public function it_wires_the_stamp_listener_on_the_task_saving_event(): void
    {
        Event::fake();

        Event::assertListening('eloquent.saving: '.Task::class, StampTaskCompletion::class);
    }
}
