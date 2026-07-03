<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Dashboard\GamificationDashboardContribution;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GamificationDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_summarises_the_player_level_and_xp(): void
    {
        $user = User::factory()->create();
        PlayerProfile::factory()->create(['user_id' => $user->id, 'total_xp' => 3000, 'level' => 7]);

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('gamification', $summary->key);
        $this->assertTrue($summary->available);
        $this->assertSame('7', $summary->metricValue);
        $this->assertNotEmpty($summary->secondaryLines);
    }

    #[Test]
    public function it_reports_level_one_for_a_user_without_profile(): void
    {
        $user = User::factory()->create();

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('1', $summary->metricValue);
    }

    #[Test]
    public function it_exposes_a_navigation_item_towards_the_player_page(): void
    {
        $item = $this->app->make(GamificationDashboardContribution::class)->navigationItem();

        $this->assertSame('player', $item->route);
    }

    #[Test]
    public function it_surfaces_the_hottest_streak_on_the_summary(): void
    {
        $user = User::factory()->create();
        Streak::factory()->create(['user_id' => $user->id, 'domain' => GamificationDomain::Sport, 'current_count' => 3]);
        Streak::factory()->create(['user_id' => $user->id, 'domain' => GamificationDomain::Todo, 'current_count' => 9]);

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertContains('Série Tâches : 9 j', $summary->secondaryLines);
    }

    #[Test]
    public function it_omits_the_streak_line_without_a_live_streak(): void
    {
        $user = User::factory()->create();
        Streak::factory()->create(['user_id' => $user->id, 'current_count' => 0]);

        $summary = $this->app->make(GamificationDashboardContribution::class)->dashboardSummary($user);

        $this->assertCount(3, $summary->secondaryLines);
    }
}
