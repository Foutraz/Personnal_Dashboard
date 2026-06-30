<?php

namespace Tests\Feature\Planning;

use Functional\Planning\Models\CalendarEvent;
use Functional\Planning\Services\AggregatedCalendarQuery;
use Functional\Planning\Services\Dto\CalendarItem;
use Functional\Planning\Services\Dto\CalendarItemSource;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AggregatedCalendarQueryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_merges_events_tasks_and_expenses_excluding_moto(): void
    {
        $user = User::factory()->create();
        CalendarEvent::factory()->create(['user_id' => $user->id, 'starts_at' => Carbon::parse('2026-07-05 10:00')]);
        Task::factory()->create(['user_id' => $user->id, 'due_at' => Carbon::parse('2026-07-06 10:00')]);
        RecurringExpense::factory()->create(['user_id' => $user->id, 'active' => true, 'next_due_at' => Carbon::parse('2026-07-07')]);

        $items = $this->app->make(AggregatedCalendarQuery::class)
            ->forUser($user, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'));

        $sources = $items->map(fn (CalendarItem $item): string => $item->source->value)->all();
        $this->assertContains('task', $sources);
        $this->assertContains('expense', $sources);
        $this->assertNotContains('moto', $sources);
        $this->assertSame(CalendarItemSource::class, $items->first()->source::class);
    }
}
