<?php

namespace Functional\RecurringExpenses\Livewire;

use Carbon\CarbonImmutable;
use Functional\RecurringExpenses\Enums\ExpenseCategory;
use Functional\RecurringExpenses\Enums\ExpenseFrequency;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\RecurringExpenses\Services\MonthlyExpenseSummary;
use Functional\RecurringExpenses\Services\NextDueDateCalculator;
use Functional\RecurringExpenses\Services\UpcomingExpensesQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

class ExpensesDashboard extends Component
{
    /**
     * The label of the expense being created inline.
     */
    public string $newLabel = '';

    /**
     * The amount of the expense being created inline.
     */
    public string $newAmount = '';

    /**
     * The category of the expense being created inline.
     */
    public string $newCategory = ExpenseCategory::Subscription->value;

    /**
     * The frequency of the expense being created inline.
     */
    public string $newFrequency = ExpenseFrequency::Monthly->value;

    /**
     * The optional day-of-month of the expense being created inline.
     */
    public ?string $newDueDay = null;

    /**
     * The first due date of the expense being created inline.
     */
    public ?string $newNextDueAt = null;

    /**
     * The first day of the month currently displayed in the calendar.
     */
    public string $calendarMonth = '';

    /**
     * Initialise the calendar to the current month.
     */
    public function mount(): void
    {
        $this->calendarMonth = Carbon::now()->startOfMonth()->toDateString();
        $this->newNextDueAt = Carbon::now()->addWeek()->toDateString();
    }

    /**
     * Create a recurring expense owned by the authenticated user from the inline form.
     */
    public function createExpense(NextDueDateCalculator $calculator): void
    {
        $this->validate([
            'newLabel' => 'required|string|max:255',
            'newAmount' => 'required|numeric|min:0',
            'newCategory' => ['required', Rule::enum(ExpenseCategory::class)],
            'newFrequency' => ['required', Rule::enum(ExpenseFrequency::class)],
            'newDueDay' => 'nullable|integer|min:1|max:31',
            'newNextDueAt' => 'required|date',
        ]);

        $frequency = ExpenseFrequency::from($this->newFrequency);
        $dueDay = $this->newDueDay !== null && $this->newDueDay !== '' ? (int) $this->newDueDay : null;

        $nextDueAt = $calculator->firstDueDate(
            $frequency,
            CarbonImmutable::parse($this->newNextDueAt),
            $dueDay,
        );

        RecurringExpense::query()->create([
            'user_id' => Auth::id(),
            'label' => $this->newLabel,
            'amount' => $this->newAmount,
            'currency' => 'EUR',
            'category' => $this->newCategory,
            'frequency' => $this->newFrequency,
            'due_day' => $dueDay,
            'starts_at' => $nextDueAt,
            'next_due_at' => $nextDueAt,
            'active' => true,
        ]);

        $this->reset('newLabel', 'newAmount', 'newDueDay');
        $this->newCategory = ExpenseCategory::Subscription->value;
        $this->newFrequency = ExpenseFrequency::Monthly->value;
        $this->newNextDueAt = Carbon::now()->addWeek()->toDateString();
    }

    /**
     * Toggle the active state of the given expense owned by the authenticated user.
     */
    public function toggleActive(string $expenseId): void
    {
        $expense = $this->ownExpense($expenseId);

        $expense?->update(['active' => ! $expense->active]);
    }

    /**
     * Delete the given expense owned by the authenticated user.
     */
    public function deleteExpense(string $expenseId): void
    {
        $this->ownExpense($expenseId)?->delete();
    }

    /**
     * Move the calendar to the previous month.
     */
    public function previousMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth)->subMonthNoOverflow()->startOfMonth()->toDateString();
    }

    /**
     * Move the calendar to the next month.
     */
    public function nextMonth(): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth)->addMonthNoOverflow()->startOfMonth()->toDateString();
    }

    /**
     * Resolve an expense ensuring it belongs to the authenticated user.
     */
    private function ownExpense(string $expenseId): ?RecurringExpense
    {
        return RecurringExpense::query()
            ->where('user_id', Auth::id())
            ->whereKey($expenseId)
            ->first();
    }

    /**
     * Load all the recurring expenses of the authenticated user.
     *
     * @return Collection<int, RecurringExpense>
     */
    public function expenses(): Collection
    {
        return RecurringExpense::query()
            ->where('user_id', Auth::id())
            ->orderBy('next_due_at')
            ->get();
    }

    /**
     * Build the calendar grid of the displayed month with due markers keyed by day.
     *
     * @param  Collection<int, RecurringExpense>  $expenses
     * @return array{weeks: array<int, array<int, array{day: int|null, expenses: array<int, RecurringExpense>}>>, label: string}
     */
    private function calendar(Collection $expenses, NextDueDateCalculator $calculator): array
    {
        $month = Carbon::parse($this->calendarMonth)->startOfMonth();

        $byDay = [];

        foreach ($expenses as $expense) {
            $occurrences = $calculator->occurrencesInMonth(
                $expense->frequency,
                $expense->starts_at ?? $expense->next_due_at,
                $month,
                $expense->due_day,
                $expense->ends_at,
            );

            foreach ($occurrences as $occurrence) {
                $byDay[$occurrence->day][] = $expense;
            }
        }

        $leadingBlanks = (int) $month->dayOfWeekIso - 1;
        $daysInMonth = $month->daysInMonth;

        $cells = [];

        for ($blank = 0; $blank < $leadingBlanks; $blank++) {
            $cells[] = ['day' => null, 'expenses' => []];
        }

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $cells[] = [
                'day' => $day,
                'expenses' => $byDay[$day] ?? [],
            ];
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = ['day' => null, 'expenses' => []];
        }

        return [
            'weeks' => array_chunk($cells, 7),
            'label' => $month->translatedFormat('F Y'),
        ];
    }

    /**
     * Render the futuristic recurring expenses calendar, timeline, tiles and donut.
     */
    #[Layout('layouts.app')]
    #[Title('Échéances')]
    public function render(MonthlyExpenseSummary $summary, UpcomingExpensesQuery $upcoming, NextDueDateCalculator $calculator): View
    {
        $expenses = $this->expenses();
        $activeExpenses = $expenses->filter(fn (RecurringExpense $expense): bool => $expense->active)->values();
        $monthlySummary = $summary->build($activeExpenses);

        $categoryLabels = array_map(fn (ExpenseCategory $category): string => $category->label(), ExpenseCategory::cases());

        return view('expenses::expenses', [
            'expenses' => $expenses,
            'calendar' => $this->calendar($activeExpenses, $calculator),
            'upcoming' => $upcoming->forUser((string) Auth::id(), 30),
            'monthlyTotal' => $monthlySummary->monthlyTotal,
            'yearlyTotal' => $monthlySummary->yearlyTotal,
            'perCategory' => $monthlySummary->perCategory,
            'activeCount' => $activeExpenses->count(),
            'categories' => ExpenseCategory::cases(),
            'frequencies' => ExpenseFrequency::cases(),
            'categoryLabels' => $categoryLabels,
            'categoryValues' => array_values($monthlySummary->perCategory),
        ]);
    }
}
