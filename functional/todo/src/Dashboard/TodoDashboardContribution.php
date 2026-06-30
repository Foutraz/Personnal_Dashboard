<?php

namespace Functional\Todo\Dashboard;

use Functional\Todo\Enums\TaskStatus;
use Functional\Todo\Models\Task;
use Functional\Todo\Services\TaskCompletionCalculator;
use Illuminate\Contracts\Auth\Authenticatable;
use Technical\Osdd\Contracts\ProvidesDashboardSummary;
use Technical\Osdd\Contracts\ProvidesNavigationItem;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;

final class TodoDashboardContribution implements ProvidesDashboardSummary, ProvidesNavigationItem
{
    private const ICON = 'M9 11l3 3 8-8M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11';

    public function __construct(private TaskCompletionCalculator $calculator) {}

    /**
     * Summarise the user's open task count and completion rate.
     */
    public function dashboardSummary(Authenticatable $user): DashboardSummary
    {
        $tasks = Task::query()->where('user_id', $user->getAuthIdentifier())->get();
        $open = $tasks->filter(fn (Task $task): bool => $task->status !== TaskStatus::Done);

        return new DashboardSummary(
            key: 'todo',
            title: 'To-Do',
            accent: 'lime',
            icon: self::ICON,
            href: route('todo'),
            order: 30,
            available: true,
            metricValue: (string) $open->count(),
            metricUnit: 'ouvertes',
            secondaryLines: [
                number_format($this->calculator->completionRate($tasks), 0, ',', ' ').' % complété',
                $tasks->count().' tâches',
            ],
        );
    }

    /**
     * Expose the to-do navigation entry.
     */
    public function navigationItem(): NavigationItem
    {
        return new NavigationItem(label: 'To-Do', route: 'todo', icon: self::ICON, order: 30);
    }
}
