<?php

namespace Tests\Feature\Goals;

use Functional\Exploration\Models\ExploredCell;
use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Models\Goal;
use Functional\Goals\Services\GoalProgressCalculator;
use Functional\Moto\Models\MotoRide;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoalMetricExtensionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_computes_moto_distance_goal_scoped_to_the_user(): void
    {
        $user = User::factory()->create();
        MotoRide::factory()->count(2)->create(['user_id' => $user->id, 'distance' => 40.0, 'started_at' => now()->subWeek()]);
        MotoRide::factory()->create(['user_id' => User::factory()->create()->id, 'distance' => 500.0, 'started_at' => now()->subWeek()]);
        $goal = Goal::factory()->create(['user_id' => $user->id, 'metric' => GoalMetric::MotoDistance, 'target_value' => 200]);

        $this->assertSame(80.0, $this->app->make(GoalProgressCalculator::class)->currentValue($goal));
    }

    #[Test]
    public function it_computes_todo_completion_rate_goal(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(3)->create(['user_id' => $user->id]);
        Task::factory()->completed()->create(['user_id' => $user->id]);
        $goal = Goal::factory()->create(['user_id' => $user->id, 'metric' => GoalMetric::TodoCompletionRate, 'target_value' => 100]);

        $this->assertSame(25.0, $this->app->make(GoalProgressCalculator::class)->currentValue($goal));
    }

    #[Test]
    public function it_computes_exploration_cells_goal(): void
    {
        $user = User::factory()->create();
        ExploredCell::factory()->count(4)->create(['user_id' => $user->id, 'first_seen_at' => now()->subWeek()]);
        $goal = Goal::factory()->create(['user_id' => $user->id, 'metric' => GoalMetric::ExplorationCells, 'target_value' => 10]);

        $this->assertSame(4.0, $this->app->make(GoalProgressCalculator::class)->currentValue($goal));
    }
}
