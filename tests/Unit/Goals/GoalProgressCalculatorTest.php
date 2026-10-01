<?php

namespace Tests\Unit\Goals;

use Functional\Exploration\Models\ExploredCell;
use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Goals\Actions\RefreshGoalStatus;
use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Enums\GoalStatus;
use Functional\Goals\Enums\GoalType;
use Functional\Goals\Exceptions\UnboundedGoalMetricException;
use Functional\Goals\Exceptions\UnsupportedGoalMetricException;
use Functional\Goals\Models\Goal;
use Functional\Goals\Services\GoalProgressCalculator;
use Functional\Moto\Models\MotoRide;
use Functional\Sport\Enums\SportType;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{GoalMetric, bool}>
     */
    public static function periodBoundMetrics(): array
    {
        return [
            'sport distance' => [GoalMetric::SportDistance, true],
            'sport elevation' => [GoalMetric::SportElevation, true],
            'sport activity count' => [GoalMetric::SportActivityCount, true],
            'sport moving time' => [GoalMetric::SportMovingTime, true],
            'finance invested capital' => [GoalMetric::FinanceInvestedCapital, true],
            'finance portfolio value' => [GoalMetric::FinancePortfolioValue, false],
            'manual' => [GoalMetric::Manual, false],
            'moto distance' => [GoalMetric::MotoDistance, true],
            'moto ride count' => [GoalMetric::MotoRideCount, true],
            'exploration cells' => [GoalMetric::ExplorationCells, true],
            'todo completion rate' => [GoalMetric::TodoCompletionRate, false],
        ];
    }

    #[Test]
    #[DataProvider('periodBoundMetrics')]
    public function it_flags_the_metrics_that_are_bound_to_a_period(GoalMetric $metric, bool $expected): void
    {
        $this->assertSame($expected, $metric->isPeriodBound());
    }

    #[Test]
    #[DataProvider('periodBoundMetrics')]
    public function it_measures_exactly_the_metrics_flagged_as_period_bound(GoalMetric $metric, bool $periodBound): void
    {
        $user = User::factory()->create();

        if (! $periodBound) {
            $this->expectException(UnboundedGoalMetricException::class);
        }

        $measured = $this->calculator->measure($metric, $user->id, Carbon::parse('2026-09-27 22:00:00', 'UTC'), Carbon::parse('2026-10-04 21:59:59', 'UTC'));

        $this->assertSame(0.0, $measured);
    }

    #[Test]
    public function it_measures_a_metric_over_inclusive_period_bounds_for_one_user_only(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        SportActivity::factory()->create(['user_id' => $user->id, 'distance' => 10000.0, 'started_at' => Carbon::parse('2026-10-04 21:30:00', 'UTC')]);
        SportActivity::factory()->create(['user_id' => $user->id, 'distance' => 5000.0, 'started_at' => Carbon::parse('2026-10-04 22:30:00', 'UTC')]);
        SportActivity::factory()->create(['user_id' => $otherUser->id, 'distance' => 7000.0, 'started_at' => Carbon::parse('2026-10-01 10:00:00', 'UTC')]);

        $measured = $this->calculator->measure(
            GoalMetric::SportDistance,
            $user->id,
            Carbon::parse('2026-09-27 22:00:00', 'UTC'),
            Carbon::parse('2026-10-04 21:59:59', 'UTC'),
        );

        $this->assertSame(10.0, $measured);
    }

    #[Test]
    public function it_includes_activities_starting_exactly_on_both_bounds(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => $user->id, 'distance' => 2000.0, 'started_at' => Carbon::parse('2026-09-27 22:00:00', 'UTC')]);
        SportActivity::factory()->create(['user_id' => $user->id, 'distance' => 3000.0, 'started_at' => Carbon::parse('2026-10-04 21:59:59', 'UTC')]);

        $measured = $this->calculator->measure(
            GoalMetric::SportDistance,
            $user->id,
            Carbon::parse('2026-09-27 22:00:00', 'UTC'),
            Carbon::parse('2026-10-04 21:59:59', 'UTC'),
        );

        $this->assertSame(5.0, $measured);
    }

    #[Test]
    public function it_measures_moto_rides_inside_the_period(): void
    {
        $user = User::factory()->create();
        MotoRide::factory()->create(['user_id' => $user->id, 'started_at' => Carbon::parse('2026-09-29 09:00:00', 'UTC')]);
        MotoRide::factory()->create(['user_id' => $user->id, 'started_at' => Carbon::parse('2026-10-02 17:00:00', 'UTC')]);
        MotoRide::factory()->create(['user_id' => $user->id, 'started_at' => Carbon::parse('2026-09-20 17:00:00', 'UTC')]);

        $measured = $this->calculator->measure(
            GoalMetric::MotoRideCount,
            $user->id,
            Carbon::parse('2026-09-27 22:00:00', 'UTC'),
            Carbon::parse('2026-10-04 21:59:59', 'UTC'),
        );

        $this->assertSame(2.0, $measured);
    }

    #[Test]
    public function it_measures_the_explored_cells_first_seen_inside_the_period(): void
    {
        $user = User::factory()->create();
        foreach (['10:100', '10:101', '10:102'] as $cellKey) {
            ExploredCell::factory()->create(['user_id' => $user->id, 'cell_key' => $cellKey, 'first_seen_at' => Carbon::parse('2026-09-30 12:00:00', 'UTC')]);
        }
        ExploredCell::factory()->create(['user_id' => $user->id, 'cell_key' => '10:103', 'first_seen_at' => Carbon::parse('2026-09-01 12:00:00', 'UTC')]);

        $measured = $this->calculator->measure(
            GoalMetric::ExplorationCells,
            $user->id,
            Carbon::parse('2026-09-27 22:00:00', 'UTC'),
            Carbon::parse('2026-10-04 21:59:59', 'UTC'),
        );

        $this->assertSame(3.0, $measured);
    }

    #[Test]
    public function it_measures_without_lower_bound_when_the_start_is_open(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => $user->id, 'distance' => 4000.0, 'started_at' => Carbon::parse('2020-01-01 10:00:00', 'UTC')]);
        SportActivity::factory()->create(['user_id' => $user->id, 'distance' => 6000.0, 'started_at' => Carbon::parse('2026-10-05 10:00:00', 'UTC')]);

        $measured = $this->calculator->measure(GoalMetric::SportDistance, $user->id, null, Carbon::parse('2026-10-04 21:59:59', 'UTC'));

        $this->assertSame(4.0, $measured);
    }

    #[Test]
    public function it_refuses_to_measure_a_metric_that_has_no_period(): void
    {
        $user = User::factory()->create();

        $this->expectException(UnboundedGoalMetricException::class);

        $this->calculator->measure(
            GoalMetric::Manual,
            $user->id,
            Carbon::parse('2026-09-27 22:00:00', 'UTC'),
            Carbon::parse('2026-10-04 21:59:59', 'UTC'),
        );
    }

    #[Test]
    public function it_names_the_unbounded_metric_in_the_exception(): void
    {
        $exception = new UnboundedGoalMetricException(GoalMetric::TodoCompletionRate);

        $this->assertSame(GoalMetric::TodoCompletionRate, $exception->metric);
    }
}
