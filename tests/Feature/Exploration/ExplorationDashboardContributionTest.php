<?php

namespace Tests\Feature\Exploration;

use Functional\Exploration\Dashboard\ExplorationDashboardContribution;
use Functional\Exploration\Models\ExploredCell;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExplorationDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_counts_the_user_explored_cells(): void
    {
        $user = User::factory()->create();
        ExploredCell::factory()->count(4)->create(['user_id' => $user->id]);
        ExploredCell::factory()->count(7)->create(['user_id' => User::factory()->create()->id]);

        $summary = $this->app->make(ExplorationDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('maps', $summary->key);
        $this->assertSame(80, $summary->order);
        $this->assertTrue($summary->available);
        $this->assertSame('4', $summary->metricValue);
        $this->assertSame('cellules', $summary->metricUnit);
    }
}
