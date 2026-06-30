<?php

namespace Tests\Feature\Goals;

use Functional\Goals\Dashboard\GoalsDashboardContribution;
use Functional\Goals\Models\Goal;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoalsDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_averages_the_user_goal_progress(): void
    {
        $user = User::factory()->create();
        Goal::factory()->manual()->count(2)->create(['user_id' => $user->id, 'target_value' => 100, 'manual_current_value' => 50]);

        $summary = $this->app->make(GoalsDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('goals', $summary->key);
        $this->assertSame(40, $summary->order);
        $this->assertTrue($summary->available);
        $this->assertSame('%', $summary->metricUnit);
        $this->assertSame('50', $summary->metricValue);
    }

    #[Test]
    public function it_reports_zero_for_a_user_without_goals(): void
    {
        $user = User::factory()->create();

        $summary = $this->app->make(GoalsDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('0', $summary->metricValue);
    }
}
