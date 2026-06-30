<?php

namespace Tests\Unit\Planning;

use Functional\Planning\Models\CalendarEvent;
use Functional\Planning\Services\AggregatedCalendarQuery;
use Functional\Planning\Services\Dto\CalendarItemSource;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class AggregatedCalendarQueryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_merges_calendar_events_and_expense_due_dates_within_the_range(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create(['provider' => IntegrationProvider::GoogleCalendar]);

        CalendarEvent::factory()->for($connection, 'connection')->create([
            'user_id' => $user->id,
            'provider' => IntegrationProvider::GoogleCalendar,
            'title' => 'In range event',
            'starts_at' => Carbon::now()->addDays(3),
        ]);

        CalendarEvent::factory()->for($connection, 'connection')->create([
            'user_id' => $user->id,
            'provider' => IntegrationProvider::GoogleCalendar,
            'title' => 'Out of range event',
            'starts_at' => Carbon::now()->addDays(40),
        ]);

        RecurringExpense::factory()->for($user)->create([
            'label' => 'Rent',
            'active' => true,
            'next_due_at' => Carbon::now()->addDays(5),
        ]);

        $items = $this->app->make(AggregatedCalendarQuery::class)
            ->forUser($user, Carbon::now()->startOfDay(), Carbon::now()->addDays(14)->endOfDay());

        $this->assertCount(2, $items);
        $this->assertSame('In range event', $items->first()->title);
        $this->assertTrue($items->contains(fn ($item): bool => $item->source === CalendarItemSource::Expense && $item->title === 'Rent'));
        $this->assertFalse($items->contains(fn ($item): bool => $item->title === 'Out of range event'));
    }

    #[Test]
    public function it_orders_items_chronologically(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create(['provider' => IntegrationProvider::OutlookCalendar]);

        CalendarEvent::factory()->for($connection, 'connection')->create([
            'user_id' => $user->id,
            'provider' => IntegrationProvider::OutlookCalendar,
            'title' => 'Later',
            'starts_at' => Carbon::now()->addDays(6),
        ]);

        RecurringExpense::factory()->for($user)->create([
            'label' => 'Earlier',
            'active' => true,
            'next_due_at' => Carbon::now()->addDays(2),
        ]);

        $items = $this->app->make(AggregatedCalendarQuery::class)
            ->forUser($user, Carbon::now()->startOfDay(), Carbon::now()->addDays(14)->endOfDay());

        $this->assertSame('Earlier', $items->first()->title);
        $this->assertSame('Later', $items->last()->title);
    }
}
