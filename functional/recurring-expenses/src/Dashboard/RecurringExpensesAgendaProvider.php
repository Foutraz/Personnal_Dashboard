<?php

namespace Functional\RecurringExpenses\Dashboard;

use Carbon\CarbonPeriod;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Technical\Osdd\Contracts\ProvidesAgendaItems;
use Technical\Osdd\Dto\AgendaItem;

final class RecurringExpensesAgendaProvider implements ProvidesAgendaItems
{
    /**
     * Map the user's active recurring expenses due within the period to agenda items.
     */
    public function agendaItems(Authenticatable $user, CarbonPeriod $period): Collection
    {
        return RecurringExpense::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('active', true)
            ->whereBetween('next_due_at', [$period->getStartDate(), $period->getEndDate()])
            ->orderBy('next_due_at')
            ->get()
            ->map(fn (RecurringExpense $expense): AgendaItem => new AgendaItem(
                id: $expense->id,
                source: 'expense',
                title: $expense->label,
                startsAt: $expense->next_due_at,
                endsAt: null,
                allDay: true,
                accent: 'lime',
                amount: (string) $expense->amount,
                href: route('recurring-expenses'),
            ));
    }
}
