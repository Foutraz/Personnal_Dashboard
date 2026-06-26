<?php

namespace Functional\Planning\Livewire;

use Functional\Planning\Models\CalendarEvent;
use Functional\Planning\Services\AggregatedCalendarQuery;
use Functional\Planning\Services\Dto\CalendarItem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;

class PlanningDashboard extends Component
{
    /**
     * The first day of the month currently displayed in the calendar.
     */
    public string $calendarMonth = '';

    /**
     * The anchor day of the week currently displayed in the calendar.
     */
    public string $calendarWeek = '';

    /**
     * The active calendar view, either month or week.
     */
    public string $view = 'month';

    /**
     * Initialise the calendar anchors to the current period.
     */
    public function mount(): void
    {
        $this->calendarMonth = Carbon::now()->startOfMonth()->toDateString();
        $this->calendarWeek = Carbon::now()->startOfWeek()->toDateString();
    }

    /**
     * Switch the active calendar view.
     */
    public function setView(string $view): void
    {
        $this->view = $view === 'week' ? 'week' : 'month';
    }

    /**
     * Move the calendar to the previous period.
     */
    public function previous(): void
    {
        if ($this->view === 'week') {
            $this->calendarWeek = Carbon::parse($this->calendarWeek)->subWeek()->startOfWeek()->toDateString();

            return;
        }

        $this->calendarMonth = Carbon::parse($this->calendarMonth)->subMonthNoOverflow()->startOfMonth()->toDateString();
    }

    /**
     * Move the calendar to the next period.
     */
    public function next(): void
    {
        if ($this->view === 'week') {
            $this->calendarWeek = Carbon::parse($this->calendarWeek)->addWeek()->startOfWeek()->toDateString();

            return;
        }

        $this->calendarMonth = Carbon::parse($this->calendarMonth)->addMonthNoOverflow()->startOfMonth()->toDateString();
    }

    /**
     * Resolve the authenticated user's connection for the given provider.
     */
    public function connectionFor(IntegrationProvider $provider): ?IntegrationConnection
    {
        return IntegrationConnection::query()
            ->where('user_id', Auth::id())
            ->where('provider', $provider)
            ->first();
    }

    /**
     * Determine the timestamp of the most recent synced event for a connection.
     */
    private function lastSyncedAt(IntegrationConnection $connection): ?Carbon
    {
        return CalendarEvent::query()
            ->where('integration_connection_id', $connection->id)
            ->latest('updated_at')
            ->value('updated_at');
    }

    /**
     * Build the displayed period boundaries from the active view.
     *
     * @return array{from: Carbon, to: Carbon, label: string}
     */
    private function period(): array
    {
        if ($this->view === 'week') {
            $start = Carbon::parse($this->calendarWeek)->startOfWeek();
            $end = (clone $start)->endOfWeek();

            return [
                'from' => $start,
                'to' => $end,
                'label' => $start->translatedFormat('d M').' – '.$end->translatedFormat('d M Y'),
            ];
        }

        $start = Carbon::parse($this->calendarMonth)->startOfMonth();

        return [
            'from' => (clone $start)->startOfWeek(),
            'to' => (clone $start)->endOfMonth()->endOfWeek(),
            'label' => $start->translatedFormat('F Y'),
        ];
    }

    /**
     * Build the calendar grid of cells with their aggregated items keyed by day.
     *
     * @param  Collection<int, CalendarItem>  $items
     * @return array<int, array<int, array{date: string, day: int, current: bool, today: bool, items: array<int, CalendarItem>}>>
     */
    private function grid(Carbon $from, Carbon $to, Collection $items): array
    {
        $byDay = $items->groupBy(fn (CalendarItem $item): string => $item->startsAt->toDateString());
        $referenceMonth = Carbon::parse($this->calendarMonth)->month;

        $cells = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $cells[] = [
                'date' => $cursor->toDateString(),
                'day' => $cursor->day,
                'current' => $this->view === 'week' || $cursor->month === $referenceMonth,
                'today' => $cursor->isToday(),
                'items' => $byDay->get($cursor->toDateString(), collect())->all(),
            ];
            $cursor->addDay();
        }

        return array_chunk($cells, 7);
    }

    /**
     * Render the planning command deck with connect cards, calendar and upcoming list.
     */
    #[Layout('layouts.app')]
    #[Title('Planning')]
    public function render(AggregatedCalendarQuery $aggregated): View
    {
        $period = $this->period();
        $userId = (string) Auth::id();

        $items = $aggregated->forUser($userId, $period['from'], $period['to']);
        $upcoming = $aggregated->forUser($userId, Carbon::now()->startOfDay(), Carbon::now()->addDays(14)->endOfDay());

        $google = $this->connectionFor(IntegrationProvider::GoogleCalendar);
        $outlook = $this->connectionFor(IntegrationProvider::OutlookCalendar);

        return view('planning::planning', [
            'view' => $this->view,
            'periodLabel' => $period['label'],
            'weeks' => $this->grid($period['from'], $period['to'], $items),
            'upcoming' => $upcoming,
            'eventsCount' => $items->count(),
            'google' => $google,
            'outlook' => $outlook,
            'googleLastSync' => $google !== null ? $this->lastSyncedAt($google) : null,
            'outlookLastSync' => $outlook !== null ? $this->lastSyncedAt($outlook) : null,
        ]);
    }
}
