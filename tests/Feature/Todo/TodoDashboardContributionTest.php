<?php

namespace Tests\Feature\Todo;

use Functional\Todo\Dashboard\TodoDashboardContribution;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TodoDashboardContributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_counts_the_user_open_tasks(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(3)->create(['user_id' => $user->id]);
        Task::factory()->completed()->create(['user_id' => $user->id]);
        Task::factory()->count(5)->create(['user_id' => User::factory()->create()->id]);

        $summary = $this->app->make(TodoDashboardContribution::class)->dashboardSummary($user);

        $this->assertSame('todo', $summary->key);
        $this->assertSame(30, $summary->order);
        $this->assertTrue($summary->available);
        $this->assertSame('3', $summary->metricValue);
        $this->assertSame('ouvertes', $summary->metricUnit);
    }
}
