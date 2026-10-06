<?php

namespace Tests\Feature\Planning;

use Carbon\CarbonImmutable;
use Functional\Planning\Livewire\PlanningDashboard;
use Functional\Planning\Models\CalendarEvent;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class PlanningTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private const TITLE = 'Quarterly review';

    private function planningWithEventAt(string $utcStartsAt, bool $allDay = false): Testable
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-03 08:00:00', 'UTC'));
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create(['provider' => IntegrationProvider::GoogleCalendar]);

        CalendarEvent::factory()->for($connection, 'connection')->create([
            'user_id' => $user->id,
            'provider' => IntegrationProvider::GoogleCalendar,
            'title' => self::TITLE,
            'starts_at' => CarbonImmutable::parse($utcStartsAt, 'UTC'),
            'ends_at' => null,
            'all_day' => $allDay,
        ]);

        return Livewire::actingAs($user, 'web')->test(PlanningDashboard::class);
    }

    private function upcomingDayLabel(string $localDay): string
    {
        return CarbonImmutable::parse($localDay)->translatedFormat('d M');
    }

    #[Test]
    public function it_shows_the_upcoming_event_time_in_the_display_timezone(): void
    {
        $planning = $this->planningWithEventAt('2026-10-04 21:30:00');

        $planning
            ->assertSee($this->upcomingDayLabel('2026-10-04').' · 23:30')
            ->assertDontSee('21:30');
    }

    #[Test]
    public function it_shows_the_upcoming_event_day_in_the_display_timezone_across_midnight(): void
    {
        $planning = $this->planningWithEventAt('2026-10-04 22:30:00');

        $planning
            ->assertSee($this->upcomingDayLabel('2026-10-05').' · 00:30')
            ->assertDontSee('22:30');
    }

    #[Test]
    public function it_shows_the_calendar_chip_time_in_the_display_timezone(): void
    {
        $planning = $this->planningWithEventAt('2026-10-04 21:30:00');

        $planning
            ->assertSeeHtml('title="'.self::TITLE.' — 23:30"')
            ->assertDontSeeHtml('title="'.self::TITLE.' — 21:30"');
    }

    #[Test]
    public function it_keeps_the_calendar_day_of_an_all_day_event_in_a_display_timezone_behind_utc(): void
    {
        Config::set('app.display_timezone', 'America/New_York');
        $planning = $this->planningWithEventAt('2026-10-05 00:00:00', allDay: true);

        $planning
            ->assertSee($this->upcomingDayLabel('2026-10-05').' · Google Calendar')
            ->assertDontSee($this->upcomingDayLabel('2026-10-04').' · Google Calendar');
    }
}
