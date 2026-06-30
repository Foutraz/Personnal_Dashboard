<?php

namespace Tests\Feature\Health;

use Functional\Health\Dashboard\HealthDashboardContribution;
use Functional\Health\Enums\MeasurementType;
use Functional\Health\Models\BodyMeasurement;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class HealthDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_summarises_the_user_latest_weight_in_kilograms(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create(['provider' => IntegrationProvider::Withings]);
        BodyMeasurement::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'type' => MeasurementType::Weight,
            'value' => 72.0,
            'measured_at' => now()->subDay(),
        ]);

        // Another user's measurement should not affect the result.
        $otherUser = User::factory()->create();
        $otherConnection = IntegrationConnection::factory()->for($otherUser)->create(['provider' => IntegrationProvider::Withings]);
        BodyMeasurement::factory()->create([
            'user_id' => $otherUser->id,
            'integration_connection_id' => $otherConnection->id,
            'type' => MeasurementType::Weight,
            'value' => 99.0,
            'measured_at' => now(),
        ]);

        $summary = $this->app->make(HealthDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('health', $summary->key);
        $this->assertSame(90, $summary->order);
        $this->assertTrue($summary->available);
        $this->assertSame('72,0', $summary->metricValue);
        $this->assertSame('kg', $summary->metricUnit);
    }

    #[Test]
    public function it_is_unavailable_until_withings_is_connected(): void
    {
        $user = User::factory()->create();

        $disconnected = $this->app->make(HealthDashboardContribution::class)->dashboardSummary($user);
        $this->assertFalse($disconnected->available);
        $this->assertSame('Connecter Withings', $disconnected->callToAction);

        IntegrationConnection::factory()->for($user)->create(['provider' => IntegrationProvider::Withings]);
        $connected = $this->app->make(HealthDashboardContribution::class)->dashboardSummary($user);
        $this->assertTrue($connected->available);
    }
}
