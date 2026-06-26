<?php

namespace Functional\Planning\Services;

use Functional\Planning\Models\CalendarEvent;
use Functional\Planning\Services\Dto\CalendarItem;
use Functional\Planning\Services\Dto\CalendarItemSource;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\RecurringExpenses\Services\UpcomingExpensesQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Technical\Integrations\Enums\IntegrationProvider;

class AggregatedCalendarQuery
{
    public function __construct(private UpcomingExpensesQuery $upcomingExpenses) {}

    /**
     * Merge the user's calendar events and recurring expense due dates within the given range.
     *
     * @return Collection<int, CalendarItem>
     */
    public function forUser(string $userId, Carbon $from, Carbon $to): Collection
    {
        $events = $this->events($userId, $from, $to);
        $expenses = $this->expenses($userId, $from, $to);

        return $events
            ->merge($expenses)
            ->sortBy(fn (CalendarItem $item): int => $item->startsAt->getTimestamp())
            ->values();
    }

    /**
     * Map the user's stored calendar events to normalized items within the range.
     *
     * @return Collection<int, CalendarItem>
     */
    private function events(string $userId, Carbon $from, Carbon $to): Collection
    {
        return CalendarEvent::query()
            ->where('user_id', $userId)
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (CalendarEvent $event): CalendarItem => new CalendarItem(
                $event->id,
                $event->provider === IntegrationProvider::GoogleCalendar ? CalendarItemSource::Google : CalendarItemSource::Outlook,
                $event->title,
                $event->starts_at,
                $event->ends_at,
                $event->all_day,
                $event->location,
                $event->external_link,
                null,
            ));
    }

    /**
     * Map the user's recurring expense due dates to normalized items within the range.
     *
     * @return Collection<int, CalendarItem>
     */
    private function expenses(string $userId, Carbon $from, Carbon $to): Collection
    {
        $withinDays = (int) max(1, $from->diffInDays($to));

        return $this->upcomingExpenses->forUser($userId, $withinDays)
            ->filter(fn (RecurringExpense $expense): bool => $expense->next_due_at->betweenIncluded($from, $to))
            ->map(fn (RecurringExpense $expense): CalendarItem => new CalendarItem(
                $expense->id,
                CalendarItemSource::Expense,
                $expense->label,
                $expense->next_due_at,
                null,
                true,
                null,
                null,
                (string) $expense->amount,
            ))
            ->values();
    }
}
