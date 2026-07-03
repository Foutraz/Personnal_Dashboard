<?php

namespace Functional\Gamification\Xp\Rules;

use Functional\Gamification\Contracts\XpRule;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Services\Dto\XpAward;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TodoTaskCompletedXpRule implements XpRule
{
    /**
     * Get the unique ledger key identifying the rule.
     */
    public function key(): string
    {
        return 'todo_task_completed';
    }

    /**
     * Get the domain the rule awards experience for.
     */
    public function domain(): GamificationDomain
    {
        return GamificationDomain::Todo;
    }

    /**
     * Award each completed task once, capped per completion day.
     *
     * @return Collection<int, XpAward>
     */
    public function awards(User $user, ?Carbon $since): Collection
    {
        $config = config('gamification.xp.todo');

        return Task::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->when($since, fn ($query) => $query->where('completed_at', '>=', $since->copy()->startOfDay()))
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get(['id', 'completed_at'])
            ->groupBy(fn (Task $task): string => $task->completed_at->toDateString())
            ->flatMap(fn (Collection $tasks): Collection => $tasks->take($config['daily_task_cap']))
            ->map(fn (Task $task): XpAward => new XpAward(
                domain: $this->domain(),
                ruleKey: $this->key(),
                sourceType: Task::class,
                sourceId: $task->id,
                points: $config['task_completed'],
                occurredAt: $task->completed_at,
            ))
            ->values();
    }
}
