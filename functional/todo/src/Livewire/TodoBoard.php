<?php

namespace Functional\Todo\Livewire;

use Functional\Todo\Enums\TaskPriority;
use Functional\Todo\Enums\TaskStatus;
use Functional\Todo\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

class TodoBoard extends Component
{
    /**
     * The title of the task being created inline.
     */
    #[Validate('required|string|max:255')]
    public string $newTitle = '';

    /**
     * The priority of the task being created inline.
     */
    public string $newPriority = TaskPriority::Medium->value;

    /**
     * The optional due date of the task being created inline.
     */
    public ?string $newDueAt = null;

    /**
     * The active status filter value.
     */
    public string $statusFilter = '';

    /**
     * The active priority filter value.
     */
    public string $priorityFilter = '';

    /**
     * Create a task owned by the authenticated user from the inline form.
     */
    public function createTask(): void
    {
        $this->validate([
            'newTitle' => 'required|string|max:255',
            'newPriority' => ['required', Rule::enum(TaskPriority::class)],
            'newDueAt' => 'nullable|date',
        ]);

        Task::query()->create([
            'user_id' => Auth::id(),
            'title' => $this->newTitle,
            'priority' => $this->newPriority,
            'status' => TaskStatus::Pending,
            'due_at' => $this->newDueAt !== null && $this->newDueAt !== '' ? $this->newDueAt : null,
            'position' => (int) Task::query()->where('user_id', Auth::id())->max('position') + 1,
        ]);

        $this->reset('newTitle', 'newDueAt');
        $this->newPriority = TaskPriority::Medium->value;

        $this->dispatch('tasks-updated');
    }

    /**
     * Toggle the completion of the given task owned by the authenticated user.
     */
    public function toggleComplete(string $taskId): void
    {
        $task = $this->ownTask($taskId);

        if ($task === null) {
            return;
        }

        if ($task->status === TaskStatus::Done) {
            $task->update(['status' => TaskStatus::Pending, 'completed_at' => null]);
            $this->dispatch('tasks-updated');

            return;
        }

        $task->update(['status' => TaskStatus::Done, 'completed_at' => now()]);

        $this->dispatch('tasks-updated');
    }

    /**
     * Cycle the priority of the given task owned by the authenticated user.
     */
    public function cyclePriority(string $taskId): void
    {
        $task = $this->ownTask($taskId);

        if ($task === null) {
            return;
        }

        $cases = TaskPriority::cases();
        $index = array_search($task->priority, $cases, true);
        $next = $cases[($index + 1) % count($cases)];

        $task->update(['priority' => $next]);
    }

    /**
     * Delete the given task owned by the authenticated user.
     */
    public function deleteTask(string $taskId): void
    {
        $this->ownTask($taskId)?->delete();

        $this->dispatch('tasks-updated');
    }

    /**
     * Reset pagination filters to their default empty values.
     */
    public function clearFilters(): void
    {
        $this->reset('statusFilter', 'priorityFilter');
    }

    /**
     * Resolve a task ensuring it belongs to the authenticated user.
     */
    private function ownTask(string $taskId): ?Task
    {
        return Task::query()
            ->where('user_id', Auth::id())
            ->whereKey($taskId)
            ->first();
    }

    /**
     * Load the authenticated user's tasks applying the active filters.
     *
     * @return Collection<int, Task>
     */
    public function tasks(): Collection
    {
        return Task::query()
            ->where('user_id', Auth::id())
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->priorityFilter !== '', fn ($query) => $query->where('priority', $this->priorityFilter))
            ->orderBy('position')
            ->latest()
            ->get();
    }

    /**
     * Render the futuristic task board with its inline form and filters.
     */
    #[Layout('layouts.app')]
    #[Title('To-Do')]
    public function render(): View
    {
        return view('todo::todo', [
            'tasks' => $this->tasks(),
            'priorities' => TaskPriority::cases(),
            'statuses' => TaskStatus::cases(),
        ]);
    }
}
