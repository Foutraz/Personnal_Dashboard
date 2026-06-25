<?php

namespace Functional\Goals\Livewire;

use Functional\Goals\Actions\RefreshGoalStatus;
use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Enums\GoalStatus;
use Functional\Goals\Enums\GoalType;
use Functional\Goals\Models\Goal;
use Functional\Goals\Services\Dto\GoalProgress;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

class GoalsDashboard extends Component
{
    /**
     * The title of the goal being created inline.
     */
    public string $newTitle = '';

    /**
     * The description of the goal being created inline.
     */
    public ?string $newDescription = null;

    /**
     * The metric of the goal being created inline.
     */
    public string $newMetric = GoalMetric::SportDistance->value;

    /**
     * The target value of the goal being created inline.
     */
    public ?string $newTargetValue = null;

    /**
     * The unit of the goal being created inline.
     */
    public ?string $newUnit = null;

    /**
     * The deadline of the goal being created inline.
     */
    public ?string $newDeadline = null;

    /**
     * The type filter applied to the goal grid.
     */
    public string $typeFilter = 'all';

    /**
     * The status filter applied to the goal grid.
     */
    public string $statusFilter = 'all';

    /**
     * The identifier of the goal whose manual progress is being edited.
     */
    public ?string $editingGoalId = null;

    /**
     * The edited manual current value of the expanded goal.
     */
    public ?string $editManualValue = null;

    /**
     * Create a goal owned by the authenticated user from the inline form.
     */
    public function createGoal(): void
    {
        $this->validate([
            'newTitle' => 'required|string|max:255',
            'newDescription' => 'nullable|string|max:2000',
            'newMetric' => ['required', Rule::enum(GoalMetric::class)],
            'newTargetValue' => 'required|numeric|min:0.0001',
            'newUnit' => 'nullable|string|max:32',
            'newDeadline' => 'nullable|date',
        ]);

        $metric = GoalMetric::from($this->newMetric);

        Goal::query()->create([
            'user_id' => Auth::id(),
            'title' => $this->newTitle,
            'description' => $this->newDescription,
            'type' => $metric->type(),
            'metric' => $metric,
            'target_value' => $this->newTargetValue,
            'manual_current_value' => $metric === GoalMetric::Manual ? 0 : null,
            'unit' => $this->newUnit !== null && $this->newUnit !== '' ? $this->newUnit : $metric->defaultUnit(),
            'starts_at' => now(),
            'deadline' => $this->newDeadline !== null && $this->newDeadline !== '' ? $this->newDeadline : null,
            'status' => GoalStatus::Active,
        ]);

        $this->reset('newTitle', 'newDescription', 'newTargetValue', 'newUnit', 'newDeadline');
        $this->newMetric = GoalMetric::SportDistance->value;
    }

    /**
     * Toggle the inline manual progress editor of the given goal.
     */
    public function startEditing(string $goalId): void
    {
        $goal = $this->ownGoal($goalId);

        if ($goal === null || $goal->metric !== GoalMetric::Manual) {
            return;
        }

        $this->editingGoalId = $goalId;
        $this->editManualValue = $goal->manual_current_value;
    }

    /**
     * Persist the edited manual current value and refresh the goal status.
     */
    public function updateManualValue(RefreshGoalStatus $refresh): void
    {
        $this->validate(['editManualValue' => 'required|numeric|min:0']);

        $goal = $this->ownGoal((string) $this->editingGoalId);

        if ($goal === null) {
            return;
        }

        $goal->update(['manual_current_value' => $this->editManualValue]);
        $refresh->handle($goal->refresh());

        $this->editingGoalId = null;
        $this->editManualValue = null;
    }

    /**
     * Archive the given goal owned by the authenticated user.
     */
    public function archiveGoal(string $goalId): void
    {
        $this->ownGoal($goalId)?->update(['status' => GoalStatus::Archived]);
    }

    /**
     * Reactivate the given goal owned by the authenticated user.
     */
    public function reactivateGoal(string $goalId): void
    {
        $this->ownGoal($goalId)?->update(['status' => GoalStatus::Active]);
    }

    /**
     * Delete the given goal owned by the authenticated user.
     */
    public function deleteGoal(string $goalId): void
    {
        $this->ownGoal($goalId)?->delete();
    }

    /**
     * Resolve a goal ensuring it belongs to the authenticated user.
     */
    private function ownGoal(string $goalId): ?Goal
    {
        return Goal::query()
            ->where('user_id', Auth::id())
            ->whereKey($goalId)
            ->first();
    }

    /**
     * Load the authenticated user's goals matching the active filters.
     *
     * @return Collection<int, Goal>
     */
    public function goals(): Collection
    {
        return Goal::query()
            ->where('user_id', Auth::id())
            ->when($this->typeFilter !== 'all', fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Render the goal grid with animated progress rings and inline CRUD.
     */
    #[Layout('layouts.app')]
    #[Title('Objectifs')]
    public function render(RefreshGoalStatus $refresh): View
    {
        $goals = $this->goals();

        /** @var Collection<string, GoalProgress> $progress */
        $progress = $goals->mapWithKeys(fn (Goal $goal): array => [$goal->id => $refresh->handle($goal)]);

        $activeCount = $goals->where('status', GoalStatus::Active)->count();
        $achievedCount = $goals->where('status', GoalStatus::Achieved)->count();
        $averageProgress = $progress->isNotEmpty()
            ? round($progress->avg(fn (GoalProgress $item): float => $item->clampedPercentage()), 0)
            : 0.0;

        return view('goals::goals', [
            'goals' => $goals,
            'progress' => $progress,
            'types' => GoalType::cases(),
            'metrics' => GoalMetric::cases(),
            'statuses' => GoalStatus::cases(),
            'activeCount' => $activeCount,
            'achievedCount' => $achievedCount,
            'averageProgress' => $averageProgress,
        ]);
    }
}
