<?php

namespace Tests\Feature\Finance;

use Functional\Finance\Dashboard\FinanceDashboardContribution;
use Functional\Finance\Models\Position;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_exposes_the_global_performance_percentage(): void
    {
        $user = User::factory()->create();
        Position::factory()->create([
            'user_id' => $user->id,
            'quantity' => 10,
            'average_buy_price' => 100,
            'current_price' => 110,
        ]);

        $summary = $this->app->make(FinanceDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('finance', $summary->key);
        $this->assertSame(20, $summary->order);
        $this->assertTrue($summary->available);
        $this->assertSame('%', $summary->metricUnit);
        $this->assertStringStartsWith('+', $summary->metricValue);
    }

    #[Test]
    public function it_reports_a_neutral_performance_for_a_user_without_positions(): void
    {
        $user = User::factory()->create();
        Position::factory()->create(['user_id' => User::factory()->create()->id]);

        $summary = $this->app->make(FinanceDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('+0,0', $summary->metricValue);
    }
}
