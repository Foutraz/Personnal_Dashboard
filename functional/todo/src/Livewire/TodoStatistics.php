<?php

namespace Functional\Todo\Livewire;

use Functional\Todo\Enums\TaskStatus;
use Functional\Todo\Models\Task;
use Functional\Todo\Services\TaskCompletionCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class TodoStatistics extends Component
{
    /**
     * Load the authenticated user's tasks.
     *
     * @return Collection<int, Task>
     */
    public function tasks(): Collection
    {
        return Task::query()
            ->where('user_id', Auth::id())
            ->get();
    }

    /**
     * Refresh the statistics when the board reports a change.
     */
    #[On('tasks-updated')]
    public function refresh(): void
    {
        //
    }

    /**
     * Render the completion statistics tiles and the status breakdown chart.
     */
    public function render(TaskCompletionCalculator $calculator): View
    {
        $tasks = $this->tasks();
        $byStatus = $calculator->countByStatus($tasks);

        return view('todo::livewire.todo-statistics', [
            'total' => $tasks->count(),
            'completionRate' => $calculator->completionRate($tasks),
            'pending' => $byStatus[TaskStatus::Pending->value],
            'inProgress' => $byStatus[TaskStatus::InProgress->value],
            'done' => $byStatus[TaskStatus::Done->value],
            'statusLabels' => array_map(fn (TaskStatus $status): string => $status->label(), TaskStatus::cases()),
            'statusValues' => array_values($byStatus),
        ]);
    }
}
