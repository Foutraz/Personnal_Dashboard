<?php

namespace Functional\Todo\Dashboard;

use Carbon\CarbonPeriod;
use Functional\Todo\Enums\TaskStatus;
use Functional\Todo\Models\Task;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Technical\Osdd\Contracts\ProvidesAgendaItems;
use Technical\Osdd\Dto\AgendaItem;

final class TodoAgendaProvider implements ProvidesAgendaItems
{
    /**
     * Map the user's uncompleted tasks due within the period to agenda items.
     */
    public function agendaItems(Authenticatable $user, CarbonPeriod $period): Collection
    {
        return Task::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('status', '!=', TaskStatus::Done)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$period->getStartDate(), $period->getEndDate()])
            ->orderBy('due_at')
            ->get()
            ->map(fn (Task $task): AgendaItem => new AgendaItem(
                id: $task->id,
                source: 'task',
                title: $task->title,
                startsAt: $task->due_at,
                endsAt: null,
                allDay: false,
                accent: 'lime',
                href: route('todo'),
            ));
    }
}
