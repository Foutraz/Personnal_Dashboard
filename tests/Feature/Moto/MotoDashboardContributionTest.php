<?php

namespace Tests\Feature\Moto;

use Functional\Moto\Dashboard\MotoDashboardContribution;
use Functional\Moto\Models\MotoRide;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MotoDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sums_the_user_ride_distance(): void
    {
        $user = User::factory()->create();
        MotoRide::factory()->count(2)->create(['user_id' => $user->id, 'distance' => 40.0]);
        MotoRide::factory()->create(['user_id' => User::factory()->create()->id, 'distance' => 500.0]);

        $summary = $this->app->make(MotoDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('moto', $summary->key);
        $this->assertSame(70, $summary->order);
        $this->assertTrue($summary->available);
        $this->assertSame('80', $summary->metricValue);
        $this->assertSame('km', $summary->metricUnit);
        $this->assertSame('2 sorties', $summary->secondaryLines[0]);
    }
}
