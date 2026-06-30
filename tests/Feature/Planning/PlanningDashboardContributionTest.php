<?php

namespace Tests\Feature\Planning;

use Functional\Planning\Dashboard\PlanningDashboardContribution;
use Functional\Planning\Models\CalendarEvent;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class PlanningDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_counts_upcoming_events_scoped_to_the_user(): void
    {
        $user = User::factory()->create();
        CalendarEvent::factory()->count(2)->create(['user_id' => $user->id, 'starts_at' => now()->addDays(3)]);
        CalendarEvent::factory()->create(['user_id' => $user->id, 'starts_at' => now()->subDay()]);
        CalendarEvent::factory()->create(['user_id' => User::factory()->create()->id, 'starts_at' => now()->addDay()]);

        $summary = $this->app->make(PlanningDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('planning', $summary->key);
        $this->assertSame(50, $summary->order);
        $this->assertSame('2', $summary->metricValue);
    }

    #[Test]
    public function it_is_unavailable_without_a_connected_calendar(): void
    {
        $user = User::factory()->create();

        $disconnected = $this->app->make(PlanningDashboardContribution::class)->dashboardSummary($user);
        $this->assertFalse($disconnected->available);
        $this->assertSame('Connecter un calendrier', $disconnected->callToAction);

        IntegrationConnection::factory()->for($user)->create(['provider' => IntegrationProvider::GoogleCalendar]);
        $connected = $this->app->make(PlanningDashboardContribution::class)->dashboardSummary($user);
        $this->assertTrue($connected->available);
    }
}
