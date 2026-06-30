<?php

namespace Tests\Feature\Planning;

use Carbon\CarbonPeriod;
use Functional\Planning\Dashboard\PlanningAgendaProvider;
use Functional\Planning\Models\CalendarEvent;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Osdd\Dto\AgendaItem;
use Tests\TestCase;

class PlanningAgendaProviderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_user_events_within_the_period(): void
    {
        $user = User::factory()->create();
        CalendarEvent::factory()->create(['user_id' => $user->id, 'provider' => IntegrationProvider::GoogleCalendar, 'starts_at' => Carbon::parse('2026-07-05 10:00')]);
        CalendarEvent::factory()->create(['user_id' => $user->id, 'starts_at' => Carbon::parse('2026-09-01 10:00')]);
        CalendarEvent::factory()->create(['user_id' => User::factory()->create()->id, 'starts_at' => Carbon::parse('2026-07-06 10:00')]);

        $items = $this->app->make(PlanningAgendaProvider::class)->agendaItems($user, CarbonPeriod::create('2026-07-01', '2026-07-31'));

        $this->assertCount(1, $items);
        $this->assertSame('google', $items->first()->source);
        $this->assertInstanceOf(AgendaItem::class, $items->first());
    }
}
