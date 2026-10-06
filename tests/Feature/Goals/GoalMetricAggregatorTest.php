<?php

namespace Tests\Feature\Goals;

use Functional\Exploration\Models\ExploredCell;
use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Exceptions\UnaggregatableGoalMetricException;
use Functional\Goals\Exceptions\UnboundedGoalMetricException;
use Functional\Goals\Services\Dto\MeasurementPeriod;
use Functional\Goals\Services\Dto\MetricAggregate;
use Functional\Goals\Services\GoalMetricAggregator;
use Functional\Moto\Models\MotoRide;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoalMetricAggregatorTest extends TestCase
{
    use RefreshDatabase;

    private GoalMetricAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aggregator = $this->app->make(GoalMetricAggregator::class);
    }

    #[Test]
    public function it_totals_the_distance_of_three_weeks_in_a_single_query(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-21 12:00:00', distance: 10000.0);
        $this->activityAt($user, '2026-09-27 21:59:59', distance: 5000.0);
        $this->activityAt($user, '2026-09-27 22:00:00', distance: 7000.0);
        $this->activityAt(User::factory()->create(), '2026-09-22 12:00:00', distance: 9000.0);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $totals = $this->aggregator->totalsPerPeriod(GoalMetric::SportDistance, $user->id, [$this->week39(), $this->week40(), $this->week41()]);

        $this->assertSame([15.0, 7.0, 0.0], $totals);
        $this->assertCount(1, DB::getQueryLog());
    }

    #[Test]
    public function it_totals_the_moving_time_in_hours(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-22 08:00:00', movingTime: 5400);
        $this->activityAt($user, '2026-09-23 08:00:00', movingTime: 1800);

        $totals = $this->aggregator->totalsPerPeriod(GoalMetric::SportMovingTime, $user->id, [$this->week39()]);

        $this->assertSame([2.0], $totals);
    }

    #[Test]
    public function it_totals_the_elevation_gain_in_meters(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-22 08:00:00', elevation: 300.0);
        $this->activityAt($user, '2026-09-23 08:00:00', elevation: 450.0);

        $totals = $this->aggregator->totalsPerPeriod(GoalMetric::SportElevation, $user->id, [$this->week39()]);

        $this->assertSame([750.0], $totals);
    }

    #[Test]
    public function it_counts_the_activities(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-21 08:00:00');
        $this->activityAt($user, '2026-09-22 08:00:00');
        $this->activityAt($user, '2026-09-23 08:00:00');

        $totals = $this->aggregator->totalsPerPeriod(GoalMetric::SportActivityCount, $user->id, [$this->week39()]);

        $this->assertSame([3.0], $totals);
    }

    #[Test]
    public function it_counts_the_moto_rides(): void
    {
        $user = User::factory()->create();
        $this->rideAt($user, '2026-09-22 08:00:00', '2026-09-22 09:00:00');
        $this->rideAt($user, '2026-09-23 08:00:00', '2026-09-23 09:00:00');

        $totals = $this->aggregator->totalsPerPeriod(GoalMetric::MotoRideCount, $user->id, [$this->week39()]);

        $this->assertSame([2.0], $totals);
    }

    #[Test]
    public function it_counts_the_cells_by_first_sighting(): void
    {
        $user = User::factory()->create();
        $this->cellAt($user, '10:100', '2026-09-28 12:00:00');
        $this->cellAt($user, '10:101', '2026-10-01 12:00:00');
        $this->cellAt($user, '10:102', '2026-10-05 12:00:00');
        $this->cellAt(User::factory()->create(), '10:100', '2026-09-28 12:00:00');

        $totals = $this->aggregator->totalsPerPeriod(GoalMetric::ExplorationCells, $user->id, [$this->week40(), $this->week41()]);

        $this->assertSame([2.0, 1.0], $totals);
    }

    #[Test]
    public function it_keeps_a_moto_ride_recorded_before_the_cutoff_only(): void
    {
        $user = User::factory()->create();
        $this->rideAt($user, '2026-09-22 08:00:00', '2026-09-23 08:00:00', distance: 100.0);
        $this->rideAt($user, '2026-09-23 08:00:00', '2026-10-02 08:00:00', distance: 50.0);
        $cutoffPeriod = $this->week39(recordedBefore: '2026-09-29 22:00:00');

        $this->assertSame([100.0], $this->aggregator->totalsPerPeriod(GoalMetric::MotoDistance, $user->id, [$cutoffPeriod]));
        $this->assertSame([150.0], $this->aggregator->totalsPerPeriod(GoalMetric::MotoDistance, $user->id, [$this->week39()]));
    }

    #[Test]
    public function it_applies_the_recording_cutoff_to_the_periods_that_carry_one_only(): void
    {
        $user = User::factory()->create();
        $this->rideAt($user, '2026-09-22 08:00:00', '2026-10-02 08:00:00', distance: 100.0);
        $this->rideAt($user, '2026-09-29 08:00:00', '2026-10-02 08:00:00', distance: 50.0);

        $totals = $this->aggregator->totalsPerPeriod(GoalMetric::MotoDistance, $user->id, [
            $this->week39(recordedBefore: '2026-09-29 22:00:00'),
            $this->week40(),
        ]);

        $this->assertSame([0.0, 50.0], $totals);
    }

    #[Test]
    public function it_compares_the_recording_cutoff_in_the_application_timezone(): void
    {
        $user = User::factory()->create();
        $this->rideAt($user, '2026-09-22 08:00:00', '2026-09-29 21:00:00', distance: 100.0);
        $this->rideAt($user, '2026-09-23 08:00:00', '2026-09-29 23:00:00', distance: 50.0);
        $parisCutoff = new MeasurementPeriod(
            Carbon::parse('2026-09-20 22:00:00', 'UTC'),
            Carbon::parse('2026-09-27 22:00:00', 'UTC'),
            Carbon::parse('2026-09-30 00:00:00', 'Europe/Paris'),
        );

        $this->assertSame([100.0], $this->aggregator->totalsPerPeriod(GoalMetric::MotoDistance, $user->id, [$parisCutoff]));
    }

    #[Test]
    public function it_never_filters_a_synced_metric_by_recording_time(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-22 08:00:00', distance: 4000.0);
        $this->cellAt($user, '10:100', '2026-09-22 08:00:00');
        $period = $this->week39(recordedBefore: '2020-01-01 00:00:00');

        $this->assertSame([4.0], $this->aggregator->totalsPerPeriod(GoalMetric::SportDistance, $user->id, [$period]));
        $this->assertSame([1.0], $this->aggregator->totalsPerPeriod(GoalMetric::ExplorationCells, $user->id, [$period]));
    }

    #[Test]
    public function it_never_counts_a_soft_deleted_ride(): void
    {
        $user = User::factory()->create();
        $this->rideAt($user, '2026-09-22 08:00:00', '2026-09-22 09:00:00', distance: 100.0);
        $this->rideAt($user, '2026-09-23 08:00:00', '2026-09-23 09:00:00', distance: 30.0)->delete();

        $this->assertSame([100.0], $this->aggregator->totalsPerPeriod(GoalMetric::MotoDistance, $user->id, [$this->week39()]));
        $this->assertSame(100.0, $this->aggregator->total(GoalMetric::MotoDistance, $user->id, null, null));
    }

    #[Test]
    public function it_never_counts_a_soft_deleted_activity(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-22 08:00:00', distance: 4000.0);
        $this->activityAt($user, '2026-09-23 08:00:00', distance: 6000.0)->delete();

        $this->assertSame([4.0], $this->aggregator->totalsPerPeriod(GoalMetric::SportDistance, $user->id, [$this->week39()]));
    }

    #[Test]
    public function it_measures_the_same_week_when_the_bounds_are_zoned_in_paris(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-27 21:30:00', distance: 1000.0);
        $this->activityAt($user, '2026-09-27 22:30:00', distance: 2000.0);
        $this->activityAt($user, '2026-10-04 21:30:00', distance: 4000.0);
        $this->activityAt($user, '2026-10-04 22:30:00', distance: 8000.0);
        $parisWeek = new MeasurementPeriod(
            Carbon::parse('2026-09-28 00:00:00', 'Europe/Paris'),
            Carbon::parse('2026-10-05 00:00:00', 'Europe/Paris'),
        );

        $this->assertSame([6.0], $this->aggregator->totalsPerPeriod(GoalMetric::SportDistance, $user->id, [$parisWeek]));
        $this->assertSame([6.0], $this->aggregator->totalsPerPeriod(GoalMetric::SportDistance, $user->id, [$this->week40()]));
    }

    #[Test]
    public function it_does_not_alter_the_bounds_it_receives(): void
    {
        $user = User::factory()->create();
        $startsAt = Carbon::parse('2026-09-28 00:00:00', 'Europe/Paris');
        $endsAt = Carbon::parse('2026-10-05 00:00:00', 'Europe/Paris');

        $this->aggregator->totalsPerPeriod(GoalMetric::SportDistance, $user->id, [new MeasurementPeriod($startsAt, $endsAt)]);
        $this->aggregator->total(GoalMetric::SportDistance, $user->id, $startsAt, $endsAt);

        $this->assertSame('Europe/Paris', $startsAt->timezoneName);
        $this->assertSame('2026-10-05 00:00:00', $endsAt->toDateTimeString());
    }

    #[Test]
    public function it_ignores_the_rows_that_fall_between_two_periods(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-10-01 08:00:00', distance: 2000.0);
        $this->activityAt($user, '2026-10-05 08:00:00', distance: 4000.0);

        $totals = $this->aggregator->totalsPerPeriod(GoalMetric::SportDistance, $user->id, [$this->week39(), $this->week41()]);

        $this->assertSame([0.0, 4.0], $totals);
    }

    #[Test]
    public function it_counts_a_row_of_two_overlapping_periods_in_the_first_only(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-10-02 08:00:00', distance: 6000.0);
        $this->activityAt($user, '2026-10-06 08:00:00', distance: 1000.0);
        $firstPeriod = $this->period('2026-09-27 22:00:00', '2026-10-04 22:00:00');
        $overlappingPeriod = $this->period('2026-10-01 00:00:00', '2026-10-08 00:00:00');

        $totals = $this->aggregator->totalsPerPeriod(GoalMetric::SportDistance, $user->id, [$firstPeriod, $overlappingPeriod]);

        $this->assertSame([6.0, 1.0], $totals);
    }

    #[Test]
    public function it_counts_a_row_in_the_first_period_that_accepts_it(): void
    {
        $user = User::factory()->create();
        $this->rideAt($user, '2026-09-30 08:00:00', '2026-10-02 08:00:00', distance: 100.0);
        $this->rideAt($user, '2026-09-30 09:00:00', '2026-09-28 08:00:00', distance: 50.0);
        $strictPeriod = $this->period('2026-09-20 22:00:00', '2026-10-04 22:00:00', '2026-09-29 22:00:00');
        $lenientPeriod = $this->period('2026-09-27 22:00:00', '2026-10-11 22:00:00', '2026-10-06 22:00:00');

        $totals = $this->aggregator->totalsPerPeriod(GoalMetric::MotoDistance, $user->id, [$strictPeriod, $lenientPeriod]);

        $this->assertSame([50.0, 100.0], $totals);
    }

    #[Test]
    public function it_returns_the_totals_in_the_order_received_whatever_the_keys_of_the_periods(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-23 08:00:00', distance: 4000.0);
        $this->activityAt($user, '2026-09-30 08:00:00', distance: 2000.0);

        $totals = $this->aggregator->totalsPerPeriod(GoalMetric::SportDistance, $user->id, [7 => $this->week40(), 3 => $this->week39()]);

        $this->assertSame([2.0, 4.0], $totals);
    }

    #[Test]
    public function it_never_writes_the_keys_of_the_periods_into_the_query(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-23 08:00:00', distance: 4000.0);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $totals = $this->aggregator->totalsPerPeriod(GoalMetric::SportDistance, $user->id, ['older' => $this->week39(), 'recent' => $this->week40()]);

        $this->assertSame([4.0, 0.0], $totals);
        $this->assertStringNotContainsString('older', DB::getQueryLog()[0]['query']);
        $this->assertStringContainsString('then 1', DB::getQueryLog()[0]['query']);
    }

    #[Test]
    public function it_returns_nothing_and_runs_no_query_for_no_period(): void
    {
        $user = User::factory()->create();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $totals = $this->aggregator->totalsPerPeriod(GoalMetric::SportDistance, $user->id, []);

        $this->assertSame([], $totals);
        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function it_refuses_the_invested_capital_which_the_finance_module_computes(): void
    {
        $user = User::factory()->create();

        $this->expectException(UnaggregatableGoalMetricException::class);

        $this->aggregator->totalsPerPeriod(GoalMetric::FinanceInvestedCapital, $user->id, [$this->week40()]);
    }

    #[Test]
    public function it_refuses_the_invested_capital_in_a_plain_total(): void
    {
        $user = User::factory()->create();

        $this->expectException(UnaggregatableGoalMetricException::class);

        $this->aggregator->total(GoalMetric::FinanceInvestedCapital, $user->id, null, null);
    }

    #[Test]
    public function it_names_the_metric_in_the_unaggregatable_exception(): void
    {
        $exception = new UnaggregatableGoalMetricException(GoalMetric::FinanceInvestedCapital);

        $this->assertSame(GoalMetric::FinanceInvestedCapital, $exception->metric);
    }

    /**
     * @return array<string, array{GoalMetric}>
     */
    public static function unboundedMetrics(): array
    {
        return [
            'finance portfolio value' => [GoalMetric::FinancePortfolioValue],
            'manual' => [GoalMetric::Manual],
            'todo completion rate' => [GoalMetric::TodoCompletionRate],
        ];
    }

    #[Test]
    #[DataProvider('unboundedMetrics')]
    public function it_refuses_a_metric_that_has_no_period_per_period(GoalMetric $metric): void
    {
        $user = User::factory()->create();

        $this->expectException(UnboundedGoalMetricException::class);

        $this->aggregator->totalsPerPeriod($metric, $user->id, [$this->week40()]);
    }

    #[Test]
    #[DataProvider('unboundedMetrics')]
    public function it_refuses_a_metric_that_has_no_period_in_a_plain_total(GoalMetric $metric): void
    {
        $user = User::factory()->create();

        $this->expectException(UnboundedGoalMetricException::class);

        $this->aggregator->total($metric, $user->id, null, null);
    }

    #[Test]
    #[DataProvider('unboundedMetrics')]
    public function it_refuses_a_metric_that_has_no_period_even_without_periods(GoalMetric $metric): void
    {
        $user = User::factory()->create();

        $this->expectException(UnboundedGoalMetricException::class);

        $this->aggregator->totalsPerPeriod($metric, $user->id, []);
    }

    #[Test]
    public function it_includes_a_row_starting_on_the_inclusive_upper_bound_of_a_total(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-10-04 21:59:59', distance: 3000.0);
        $this->activityAt($user, '2026-10-04 22:00:00', distance: 4000.0);

        $total = $this->aggregator->total(
            GoalMetric::SportDistance,
            $user->id,
            Carbon::parse('2026-09-27 22:00:00', 'UTC'),
            Carbon::parse('2026-10-04 21:59:59', 'UTC'),
        );

        $this->assertSame(3.0, $total);
    }

    #[Test]
    public function it_includes_a_row_starting_on_the_inclusive_lower_bound_of_a_total(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-27 21:59:59', distance: 8000.0);
        $this->activityAt($user, '2026-09-27 22:00:00', distance: 2000.0);

        $total = $this->aggregator->total(
            GoalMetric::SportDistance,
            $user->id,
            Carbon::parse('2026-09-27 22:00:00', 'UTC'),
            Carbon::parse('2026-10-04 21:59:59', 'UTC'),
        );

        $this->assertSame(2.0, $total);
    }

    #[Test]
    public function it_leaves_a_total_open_on_a_missing_bound(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2020-01-01 10:00:00', distance: 4000.0);
        $this->activityAt($user, '2026-10-05 10:00:00', distance: 6000.0);

        $this->assertSame(10.0, $this->aggregator->total(GoalMetric::SportDistance, $user->id, null, null));
        $this->assertSame(4.0, $this->aggregator->total(GoalMetric::SportDistance, $user->id, null, Carbon::parse('2026-10-04 21:59:59', 'UTC')));
        $this->assertSame(6.0, $this->aggregator->total(GoalMetric::SportDistance, $user->id, Carbon::parse('2026-10-04 22:00:00', 'UTC'), null));
    }

    #[Test]
    public function it_totals_for_the_requested_user_only(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-10-01 10:00:00', distance: 2000.0);
        $this->activityAt(User::factory()->create(), '2026-10-01 10:00:00', distance: 9000.0);

        $this->assertSame(2.0, $this->aggregator->total(GoalMetric::SportDistance, $user->id, null, null));
    }

    #[Test]
    public function it_totals_nothing_as_zero(): void
    {
        $user = User::factory()->create();

        foreach ([GoalMetric::SportDistance, GoalMetric::SportMovingTime, GoalMetric::MotoDistance, GoalMetric::MotoRideCount, GoalMetric::ExplorationCells] as $metric) {
            $this->assertSame(0.0, $this->aggregator->total($metric, $user->id, null, null));
        }
    }

    #[Test]
    public function it_totals_over_the_zoned_bounds_in_the_application_timezone(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-27 21:30:00', distance: 1000.0);
        $this->activityAt($user, '2026-09-27 22:30:00', distance: 2000.0);

        $total = $this->aggregator->total(
            GoalMetric::SportDistance,
            $user->id,
            Carbon::parse('2026-09-28 00:00:00', 'Europe/Paris'),
            Carbon::parse('2026-10-05 00:00:00', 'Europe/Paris'),
        );

        $this->assertSame(2.0, $total);
    }

    #[Test]
    public function it_totals_a_moto_ride_whatever_its_recording_time(): void
    {
        $user = User::factory()->create();
        $this->rideAt($user, '2026-09-22 08:00:00', '2026-10-02 08:00:00', distance: 100.0);

        $total = $this->aggregator->total(
            GoalMetric::MotoDistance,
            $user->id,
            Carbon::parse('2026-09-20 22:00:00', 'UTC'),
            Carbon::parse('2026-09-27 21:59:59', 'UTC'),
        );

        $this->assertSame(100.0, $total);
    }

    #[Test]
    public function it_totals_in_a_single_summing_query(): void
    {
        $user = User::factory()->create();
        $this->rideAt($user, '2026-09-22 08:00:00', '2026-09-22 09:00:00', distance: 100.0);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->aggregator->total(GoalMetric::MotoDistance, $user->id, null, null);

        $this->assertCount(1, DB::getQueryLog());
        $this->assertStringContainsString('sum(', DB::getQueryLog()[0]['query']);
    }

    #[Test]
    public function it_wraps_the_summed_column_with_the_grammar_of_the_connection(): void
    {
        $grammar = DB::connection()->getQueryGrammar();
        $aggregate = new MetricAggregate(SportActivity::class, 'started_at', 'total_elevation_gain', 1.0);

        $this->assertSame("sum({$grammar->wrap('total_elevation_gain')})", $aggregate->expression($grammar));
    }

    #[Test]
    public function it_counts_rows_when_there_is_no_summed_column(): void
    {
        $aggregate = new MetricAggregate(ExploredCell::class, 'first_seen_at', null, 1.0);

        $this->assertSame('count(*)', $aggregate->expression(DB::connection()->getQueryGrammar()));
    }

    #[Test]
    public function it_has_no_recorded_column_by_default(): void
    {
        $aggregate = new MetricAggregate(SportActivity::class, 'started_at', 'distance', 1000.0);

        $this->assertNull($aggregate->recordedColumn);
    }

    private function week39(?string $recordedBefore = null): MeasurementPeriod
    {
        return $this->period('2026-09-20 22:00:00', '2026-09-27 22:00:00', $recordedBefore);
    }

    private function week40(?string $recordedBefore = null): MeasurementPeriod
    {
        return $this->period('2026-09-27 22:00:00', '2026-10-04 22:00:00', $recordedBefore);
    }

    private function week41(?string $recordedBefore = null): MeasurementPeriod
    {
        return $this->period('2026-10-04 22:00:00', '2026-10-11 22:00:00', $recordedBefore);
    }

    private function period(string $startsAtUtc, string $endsAtUtc, ?string $recordedBeforeUtc = null): MeasurementPeriod
    {
        return new MeasurementPeriod(
            Carbon::parse($startsAtUtc, 'UTC'),
            Carbon::parse($endsAtUtc, 'UTC'),
            $recordedBeforeUtc === null ? null : Carbon::parse($recordedBeforeUtc, 'UTC'),
        );
    }

    private function activityAt(User $user, string $startedAtUtc, float $distance = 1000.0, int $movingTime = 600, float $elevation = 0.0): SportActivity
    {
        return SportActivity::factory()->create([
            'user_id' => $user->id,
            'distance' => $distance,
            'moving_time' => $movingTime,
            'total_elevation_gain' => $elevation,
            'started_at' => Carbon::parse($startedAtUtc, 'UTC'),
        ]);
    }

    private function rideAt(User $user, string $startedAtUtc, string $recordedAtUtc, float $distance = 10.0): MotoRide
    {
        return MotoRide::factory()->create([
            'user_id' => $user->id,
            'distance' => $distance,
            'started_at' => Carbon::parse($startedAtUtc, 'UTC'),
            'recorded_at' => Carbon::parse($recordedAtUtc, 'UTC'),
        ]);
    }

    private function cellAt(User $user, string $cellKey, string $firstSeenAtUtc): ExploredCell
    {
        return ExploredCell::factory()->create([
            'user_id' => $user->id,
            'cell_key' => $cellKey,
            'first_seen_at' => Carbon::parse($firstSeenAtUtc, 'UTC'),
        ]);
    }
}
