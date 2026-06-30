<?php

namespace Tests\Feature\Sport;

use Functional\Sport\Dashboard\SportDashboardContribution;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class SportDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_summarises_the_user_distance_in_kilometres(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->count(2)->create(['user_id' => $user->id, 'distance' => 5000.0]);
        SportActivity::factory()->create(['user_id' => User::factory()->create()->id, 'distance' => 99000.0]);

        $summary = $this->app->make(SportDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('sport', $summary->key);
        $this->assertSame(10, $summary->order);
        $this->assertSame('10', $summary->metricValue);
        $this->assertSame('km', $summary->metricUnit);
    }

    #[Test]
    public function it_is_unavailable_until_strava_is_connected(): void
    {
        $user = User::factory()->create();

        $disconnected = $this->app->make(SportDashboardContribution::class)->dashboardSummary($user);
        $this->assertFalse($disconnected->available);
        $this->assertSame('Connecter Strava', $disconnected->callToAction);

        IntegrationConnection::factory()->for($user)->create(['provider' => IntegrationProvider::Strava]);
        $connected = $this->app->make(SportDashboardContribution::class)->dashboardSummary($user);
        $this->assertTrue($connected->available);
    }
}
