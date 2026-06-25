<?php

namespace Tests\Unit\Todo;

use Functional\Todo\Enums\TaskPriority;
use Functional\Todo\Enums\TaskStatus;
use Functional\Todo\Models\Task;
use Functional\Todo\Services\TaskCompletionCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaskCompletionCalculatorTest extends TestCase
{
    private TaskCompletionCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new TaskCompletionCalculator;
    }

    #[Test]
    public function it_counts_tasks_by_status(): void
    {
        $counts = $this->calculator->countByStatus($this->tasks());

        $this->assertSame(2, $counts[TaskStatus::Pending->value]);
        $this->assertSame(1, $counts[TaskStatus::InProgress->value]);
        $this->assertSame(2, $counts[TaskStatus::Done->value]);
    }

    #[Test]
    public function it_counts_tasks_by_priority(): void
    {
        $counts = $this->calculator->countByPriority($this->tasks());

        $this->assertSame(3, $counts[TaskPriority::Low->value]);
        $this->assertSame(2, $counts[TaskPriority::High->value]);
        $this->assertSame(0, $counts[TaskPriority::Urgent->value]);
    }

    #[Test]
    public function it_computes_the_completion_rate(): void
    {
        $this->assertSame(40.0, $this->calculator->completionRate($this->tasks()));
    }

    #[Test]
    public function it_returns_zero_completion_rate_without_tasks(): void
    {
        $this->assertSame(0.0, $this->calculator->completionRate(new Collection));
    }

    #[Test]
    public function it_aggregates_completed_tasks_by_month(): void
    {
        $completed = $this->calculator->completedByPeriod($this->tasks(), 'Y-m');

        $this->assertSame(1, $completed['2026-01']);
        $this->assertSame(1, $completed['2026-02']);
    }

    /**
     * Build a hand-crafted collection of tasks.
     *
     * @return Collection<int, Task>
     */
    private function tasks(): Collection
    {
        return new Collection([
            $this->makeTask(TaskStatus::Pending, TaskPriority::Low, null),
            $this->makeTask(TaskStatus::Pending, TaskPriority::Low, null),
            $this->makeTask(TaskStatus::InProgress, TaskPriority::Low, null),
            $this->makeTask(TaskStatus::Done, TaskPriority::High, '2026-01-15'),
            $this->makeTask(TaskStatus::Done, TaskPriority::High, '2026-02-20'),
        ]);
    }

    private function makeTask(TaskStatus $status, TaskPriority $priority, ?string $completedAt): Task
    {
        return (new Task)->forceFill([
            'status' => $status,
            'priority' => $priority,
            'completed_at' => $completedAt !== null ? Carbon::parse($completedAt) : null,
        ]);
    }
}
