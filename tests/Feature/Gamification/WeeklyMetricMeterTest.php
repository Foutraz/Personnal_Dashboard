<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Gamification\Services\WeeklyMetricMeter;
use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Exceptions\UnboundedGoalMetricException;
use Functional\Moto\Models\MotoRide;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WeeklyMetricMeterTest extends TestCase
{
    use RefreshDatabase;

    private const GRACE_HOURS = 48;

    private WeeklyMetricMeter $meter;

    private GamificationWeek $week;

    protected function setUp(): void
    {
        parent::setUp();

        $this->meter = $this->app->make(WeeklyMetricMeter::class);
        $this->week = $this->app->make(GamificationCalendar::class)->weekOf(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
    }

    #[Test]
    public function it_measures_the_distance_of_a_game_week_in_kilometers(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-10-04 21:30:00', distance: 10000.0);
        $this->activityAt($user, '2026-10-04 22:30:00', distance: 5000.0);

        $this->assertSame('2026-W40', $this->week->key());
        $this->assertSame(10.0, $this->measureWeek($user, GoalMetric::SportDistance, $this->week));
    }

    #[Test]
    public function it_measures_the_moving_time_of_a_game_week_in_hours(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-10-01 08:00:00', movingTime: 5400);

        $this->assertSame(1.5, $this->measureWeek($user, GoalMetric::SportMovingTime, $this->week));
    }

    #[Test]
    public function it_rounds_the_measure_to_two_decimals(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-10-01 08:00:00', distance: 10001.0);

        $this->assertSame(10.0, $this->measureWeek($user, GoalMetric::SportDistance, $this->week));
    }

    #[Test]
    public function it_measures_the_same_week_when_the_bounds_are_zoned_in_paris(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-10-04 21:30:00', distance: 10000.0);
        $this->activityAt($user, '2026-10-04 22:30:00', distance: 5000.0);

        $measured = $this->meter->measure(
            $user,
            GoalMetric::SportDistance,
            Carbon::parse('2026-09-28 00:00:00', 'Europe/Paris'),
            Carbon::parse('2026-10-05 00:00:00', 'Europe/Paris'),
            Carbon::parse('2026-10-07 00:00:00', 'Europe/Paris'),
        );

        $this->assertSame(10.0, $measured);
    }

    #[Test]
    public function it_does_not_alter_the_bounds_it_receives(): void
    {
        $user = User::factory()->create();
        $startsAt = Carbon::parse('2026-09-28 00:00:00', 'Europe/Paris');
        $endsAt = Carbon::parse('2026-10-05 00:00:00', 'Europe/Paris');
        $closesAt = Carbon::parse('2026-10-07 00:00:00', 'Europe/Paris');

        $this->meter->measure($user, GoalMetric::SportDistance, $startsAt, $endsAt, $closesAt);

        $this->assertSame('Europe/Paris', $startsAt->timezoneName);
        $this->assertSame('2026-10-05 00:00:00', $endsAt->toDateTimeString());
        $this->assertSame('2026-10-07 00:00:00', $closesAt->toDateTimeString());
    }

    #[Test]
    public function it_includes_the_start_of_the_week_and_excludes_its_end(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-27 22:00:00', distance: 2000.0);
        $this->activityAt($user, '2026-10-04 21:59:59', distance: 3000.0);
        $this->activityAt($user, '2026-10-04 22:00:00', distance: 4000.0);
        $this->activityAt($user, '2026-09-27 21:59:59', distance: 8000.0);

        $this->assertSame(5.0, $this->measureWeek($user, GoalMetric::SportDistance, $this->week));
    }

    #[Test]
    public function it_ignores_the_activities_of_other_users(): void
    {
        $user = User::factory()->create();
        $this->activityAt(User::factory()->create(), '2026-10-01 08:00:00', distance: 7000.0);

        $this->assertSame(0.0, $this->measureWeek($user, GoalMetric::SportDistance, $this->week));
    }

    #[Test]
    public function it_refuses_a_metric_that_has_no_period(): void
    {
        $user = User::factory()->create();

        $this->expectException(UnboundedGoalMetricException::class);

        $this->measureWeek($user, GoalMetric::TodoCompletionRate, $this->week);
    }

    #[Test]
    public function it_counts_a_moto_ride_recorded_just_before_the_closing_instant(): void
    {
        $user = User::factory()->create();
        $this->rideAt($user, '2026-10-04 08:00:00', '2026-10-06 21:59:59', distance: 160.0);

        $this->assertSame(160.0, $this->measureWeek($user, GoalMetric::MotoDistance, $this->week));
    }

    #[Test]
    public function it_ignores_a_moto_ride_recorded_at_the_closing_instant(): void
    {
        $user = User::factory()->create();
        $this->rideAt($user, '2026-10-04 08:00:00', '2026-10-06 22:00:00', distance: 160.0);

        $this->assertSame(0.0, $this->measureWeek($user, GoalMetric::MotoDistance, $this->week));
    }

    #[Test]
    public function it_counts_the_moto_rides_recorded_before_the_closing_instant_only(): void
    {
        $user = User::factory()->create();
        $this->rideAt($user, '2026-10-01 08:00:00', '2026-10-01 09:00:00');
        $this->rideAt($user, '2026-10-02 08:00:00', '2026-10-06 22:00:01');

        $this->assertSame(1.0, $this->measureWeek($user, GoalMetric::MotoRideCount, $this->week));
    }

    #[Test]
    public function it_counts_a_synced_activity_whatever_its_creation_instant(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-10-04 15:00:00', distance: 30000.0, createdAtUtc: '2026-10-09 01:00:00');

        $this->assertSame(30.0, $this->measureWeek($user, GoalMetric::SportDistance, $this->week));
    }

    #[Test]
    public function it_measures_the_history_of_the_given_weeks_in_the_order_received(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);

        $values = $this->meter->history($user, GoalMetric::SportDistance, $this->historyWeeks(), self::GRACE_HOURS);

        $this->assertSame([10.0, 20.0, 30.0, 40.0], $values);
        $this->assertSame(
            [40.0, 10.0],
            $this->meter->history($user, GoalMetric::SportDistance, [$this->week->previous(1), $this->week->previous(4)], self::GRACE_HOURS),
        );
    }

    #[Test]
    public function it_measures_the_history_in_a_single_query(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->meter->history($user, GoalMetric::SportDistance, $this->historyWeeks(), self::GRACE_HOURS);

        $this->assertCount(1, DB::getQueryLog());
    }

    #[Test]
    public function it_leaves_a_late_moto_ride_out_of_the_history(): void
    {
        $user = User::factory()->create();
        $this->motoHistory($user, lateRecordingOfTheThirdWeek: '2026-09-22 22:00:00');
        DB::flushQueryLog();
        DB::enableQueryLog();

        $values = $this->meter->history($user, GoalMetric::MotoDistance, $this->historyWeeks(), self::GRACE_HOURS);

        $this->assertSame([100.0, 120.0, 0.0, 160.0], $values);
        $this->assertCount(1, DB::getQueryLog());
    }

    #[Test]
    public function it_keeps_a_moto_ride_recorded_one_second_before_the_closing_of_its_week_in_the_history(): void
    {
        $user = User::factory()->create();
        $this->motoHistory($user, lateRecordingOfTheThirdWeek: '2026-09-22 21:59:59');

        $values = $this->meter->history($user, GoalMetric::MotoDistance, $this->historyWeeks(), self::GRACE_HOURS);

        $this->assertSame([100.0, 120.0, 140.0, 160.0], $values);
    }

    #[Test]
    public function it_applies_the_given_grace_to_the_closing_of_every_historic_week(): void
    {
        $user = User::factory()->create();
        $this->motoHistory($user, lateRecordingOfTheThirdWeek: '2026-09-23 10:00:00');

        $this->assertSame(
            [100.0, 120.0, 0.0, 160.0],
            $this->meter->history($user, GoalMetric::MotoDistance, $this->historyWeeks(), 48),
        );
        $this->assertSame(
            [100.0, 120.0, 140.0, 160.0],
            $this->meter->history($user, GoalMetric::MotoDistance, $this->historyWeeks(), 72),
        );
    }

    #[Test]
    public function it_keeps_a_synced_activity_created_after_the_closing_of_its_week_in_the_history(): void
    {
        $user = User::factory()->create();
        $this->sportHistory($user, createdAtUtc: '2026-09-30 10:00:00');

        $values = $this->meter->history($user, GoalMetric::SportDistance, $this->historyWeeks(), self::GRACE_HOURS);

        $this->assertSame([10.0, 20.0, 30.0, 40.0], $values);
    }

    #[Test]
    public function it_rounds_each_value_of_the_history_to_two_decimals(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-02 12:00:00', distance: 10001.0);
        $this->activityAt($user, '2026-09-09 12:00:00', distance: 20004.0);

        $values = $this->meter->history($user, GoalMetric::SportDistance, $this->historyWeeks(), self::GRACE_HOURS);

        $this->assertSame([10.0, 20.0, 0.0, 0.0], $values);
    }

    #[Test]
    public function it_returns_an_empty_history_without_week_and_runs_no_query(): void
    {
        $user = User::factory()->create();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $values = $this->meter->history($user, GoalMetric::SportDistance, [], self::GRACE_HOURS);

        $this->assertSame([], $values);
        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function it_ignores_the_rides_of_other_users_in_the_history(): void
    {
        $user = User::factory()->create();
        $this->motoHistory(User::factory()->create());

        $values = $this->meter->history($user, GoalMetric::MotoDistance, $this->historyWeeks(), self::GRACE_HOURS);

        $this->assertSame([0.0, 0.0, 0.0, 0.0], $values);
    }

    #[Test]
    public function it_refuses_a_history_of_a_metric_that_has_no_period(): void
    {
        $user = User::factory()->create();

        $this->expectException(UnboundedGoalMetricException::class);

        $this->meter->history($user, GoalMetric::TodoCompletionRate, $this->historyWeeks(), self::GRACE_HOURS);
    }

    private function measureWeek(User $user, GoalMetric $metric, GamificationWeek $week): float
    {
        return $this->meter->measure($user, $metric, $week->startsAt, $week->endsAt, $week->closesAt(self::GRACE_HOURS));
    }

    /**
     * @return list<GamificationWeek>
     */
    private function historyWeeks(): array
    {
        return [$this->week->previous(4), $this->week->previous(3), $this->week->previous(2), $this->week->previous(1)];
    }

    private function sportHistory(User $user, ?string $createdAtUtc = null): void
    {
        foreach (['2026-09-02' => 10000.0, '2026-09-09' => 20000.0, '2026-09-16' => 30000.0, '2026-09-23' => 40000.0] as $day => $meters) {
            $this->activityAt($user, "{$day} 12:00:00", distance: $meters, createdAtUtc: $createdAtUtc);
        }
    }

    private function motoHistory(User $user, string $lateRecordingOfTheThirdWeek = '2026-09-16 12:00:00'): void
    {
        $this->rideAt($user, '2026-09-02 12:00:00', '2026-09-02 12:00:00', distance: 100.0);
        $this->rideAt($user, '2026-09-09 12:00:00', '2026-09-09 12:00:00', distance: 120.0);
        $this->rideAt($user, '2026-09-16 12:00:00', $lateRecordingOfTheThirdWeek, distance: 140.0);
        $this->rideAt($user, '2026-09-23 12:00:00', '2026-09-23 12:00:00', distance: 160.0);
    }

    private function activityAt(User $user, string $startedAtUtc, float $distance = 1000.0, int $movingTime = 600, ?string $createdAtUtc = null): SportActivity
    {
        return SportActivity::factory()->create([
            'user_id' => $user->id,
            'distance' => $distance,
            'moving_time' => $movingTime,
            'started_at' => Carbon::parse($startedAtUtc, 'UTC'),
            ...$this->creationAttributes($createdAtUtc),
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

    /**
     * @return array<string, Carbon>
     */
    private function creationAttributes(?string $createdAtUtc): array
    {
        return $createdAtUtc === null ? [] : ['created_at' => Carbon::parse($createdAtUtc, 'UTC')];
    }
}
