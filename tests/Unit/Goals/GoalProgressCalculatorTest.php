<?php

namespace Tests\Unit\Goals;

use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Goals\Actions\RefreshGoalStatus;
use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Enums\GoalStatus;
use Functional\Goals\Enums\GoalType;
use Functional\Goals\Exceptions\UnsupportedGoalMetricException;
use Functional\Goals\Models\Goal;
use Functional\Goals\Services\GoalProgressCalculator;
use Functional\Sport\Enums\SportType;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoalProgressCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private GoalProgressCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(GoalProgressCalculator::class);
    }

    #[Test]
    public function it_computes_sport_distance_progress_in_kilometers(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->count(2)->create([
            'user_id' => $user->id,
            'distance' => 5000.0,
            'started_at' => now()->subDays(3),
        ]);

        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'type' => GoalType::Sport,
            'metric' => GoalMetric::SportDistance,
            'target_value' => 20,
            'starts_at' => now()->subMonth(),
            'deadline' => now()->addMonth(),
            'manual_current_value' => null,
        ]);

        $progress = $this->calculator->progress($goal);

        $this->assertSame(10.0, $progress->currentValue);
        $this->assertSame(50.0, $progress->percentage);
    }

    #[Test]
    public function it_computes_sport_activity_count_progress(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->count(3)->create([
            'user_id' => $user->id,
            'started_at' => now()->subDays(2),
        ]);

        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'type' => GoalType::Sport,
            'metric' => GoalMetric::SportActivityCount,
            'target_value' => 6,
            'starts_at' => now()->subMonth(),
            'deadline' => now()->addMonth(),
            'manual_current_value' => null,
        ]);

        $progress = $this->calculator->progress($goal);

        $this->assertSame(3.0, $progress->currentValue);
        $this->assertSame(50.0, $progress->percentage);
    }

    #[Test]
    public function it_computes_sport_elevation_progress(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create([
            'user_id' => $user->id,
            'total_elevation_gain' => 800.0,
            'started_at' => now()->subDay(),
        ]);

        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'type' => GoalType::Sport,
            'metric' => GoalMetric::SportElevation,
            'target_value' => 1000,
            'starts_at' => now()->subMonth(),
            'deadline' => now()->addMonth(),
            'manual_current_value' => null,
        ]);

        $this->assertSame(800.0, $this->calculator->progress($goal)->currentValue);
    }

    #[Test]
    public function it_computes_finance_invested_capital_progress(): void
    {
        $user = User::factory()->create();
        $position = Position::factory()->create(['user_id' => $user->id]);
        InvestmentTransaction::factory()->create([
            'position_id' => $position->id,
            'user_id' => $user->id,
            'type' => TransactionType::Buy,
            'quantity' => 10,
            'unit_price' => 100,
            'executed_at' => now()->subDays(5),
        ]);

        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'type' => GoalType::Finance,
            'metric' => GoalMetric::FinanceInvestedCapital,
            'target_value' => 2000,
            'starts_at' => now()->subMonth(),
            'deadline' => now()->addMonth(),
            'manual_current_value' => null,
        ]);

        $progress = $this->calculator->progress($goal);

        $this->assertSame(1000.0, $progress->currentValue);
        $this->assertSame(50.0, $progress->percentage);
    }

    #[Test]
    public function it_computes_finance_portfolio_value_progress(): void
    {
        $user = User::factory()->create();
        Position::factory()->create([
            'user_id' => $user->id,
            'quantity' => 10,
            'current_price' => 150,
        ]);

        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'type' => GoalType::Finance,
            'metric' => GoalMetric::FinancePortfolioValue,
            'target_value' => 3000,
            'manual_current_value' => null,
        ]);

        $progress = $this->calculator->progress($goal);

        $this->assertSame(1500.0, $progress->currentValue);
        $this->assertSame(50.0, $progress->percentage);
    }

    #[Test]
    public function it_uses_the_manual_current_value_for_personal_goals(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'type' => GoalType::Personal,
            'metric' => GoalMetric::Manual,
            'target_value' => 50,
            'manual_current_value' => 30,
        ]);

        $progress = $this->calculator->progress($goal);

        $this->assertSame(30.0, $progress->currentValue);
        $this->assertSame(60.0, $progress->percentage);
    }

    #[Test]
    public function it_flips_status_to_achieved_when_progress_reaches_the_threshold(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'type' => GoalType::Personal,
            'metric' => GoalMetric::Manual,
            'target_value' => 50,
            'manual_current_value' => 50,
            'status' => GoalStatus::Active,
        ]);

        app(RefreshGoalStatus::class)->handle($goal);

        $this->assertSame(GoalStatus::Achieved, $goal->refresh()->status);
    }

    #[Test]
    public function it_keeps_status_active_below_the_threshold(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'type' => GoalType::Personal,
            'metric' => GoalMetric::Manual,
            'target_value' => 50,
            'manual_current_value' => 10,
            'status' => GoalStatus::Active,
        ]);

        app(RefreshGoalStatus::class)->handle($goal);

        $this->assertSame(GoalStatus::Active, $goal->refresh()->status);
    }

    #[Test]
    public function it_guards_an_invalid_metric_and_type_combination(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'type' => GoalType::Sport,
            'metric' => GoalMetric::Manual,
            'target_value' => 10,
        ]);

        $this->expectException(UnsupportedGoalMetricException::class);

        $this->calculator->progress($goal);
    }

    #[Test]
    public function it_scopes_sport_progress_to_the_goal_period(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create([
            'user_id' => $user->id,
            'sport_type' => SportType::Run,
            'distance' => 10000.0,
            'started_at' => now()->subYears(2),
        ]);

        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'type' => GoalType::Sport,
            'metric' => GoalMetric::SportDistance,
            'target_value' => 10,
            'starts_at' => now()->subMonth(),
            'deadline' => now()->addMonth(),
            'manual_current_value' => null,
        ]);

        $this->assertSame(0.0, $this->calculator->progress($goal)->currentValue);
    }
}
