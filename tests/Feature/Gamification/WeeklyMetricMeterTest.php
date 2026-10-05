<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Gamification\Services\WeeklyMetricMeter;
use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Exceptions\UnboundedGoalMetricException;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WeeklyMetricMeterTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertSame(10.0, $this->meter->measureWeek($user, GoalMetric::SportDistance, $this->week));
    }

    #[Test]
    public function it_measures_the_moving_time_of_a_game_week_in_hours(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-10-01 08:00:00', movingTime: 5400);

        $this->assertSame(1.5, $this->meter->measureWeek($user, GoalMetric::SportMovingTime, $this->week));
    }

    #[Test]
    public function it_rounds_the_measure_to_two_decimals(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-10-01 08:00:00', distance: 10001.0);

        $this->assertSame(10.0, $this->meter->measureWeek($user, GoalMetric::SportDistance, $this->week));
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
        );

        $this->assertSame(10.0, $measured);
    }

    #[Test]
    public function it_does_not_alter_the_bounds_it_receives(): void
    {
        $user = User::factory()->create();
        $startsAt = Carbon::parse('2026-09-28 00:00:00', 'Europe/Paris');
        $endsAt = Carbon::parse('2026-10-05 00:00:00', 'Europe/Paris');

        $this->meter->measure($user, GoalMetric::SportDistance, $startsAt, $endsAt);

        $this->assertSame('Europe/Paris', $startsAt->timezoneName);
        $this->assertSame('2026-10-05 00:00:00', $endsAt->toDateTimeString());
    }

    #[Test]
    public function it_includes_the_start_of_the_week_and_excludes_its_end(): void
    {
        $user = User::factory()->create();
        $this->activityAt($user, '2026-09-27 22:00:00', distance: 2000.0);
        $this->activityAt($user, '2026-10-04 21:59:59', distance: 3000.0);
        $this->activityAt($user, '2026-10-04 22:00:00', distance: 4000.0);
        $this->activityAt($user, '2026-09-27 21:59:59', distance: 8000.0);

        $this->assertSame(5.0, $this->meter->measureWeek($user, GoalMetric::SportDistance, $this->week));
    }

    #[Test]
    public function it_ignores_the_activities_of_other_users(): void
    {
        $user = User::factory()->create();
        $this->activityAt(User::factory()->create(), '2026-10-01 08:00:00', distance: 7000.0);

        $this->assertSame(0.0, $this->meter->measureWeek($user, GoalMetric::SportDistance, $this->week));
    }

    #[Test]
    public function it_refuses_a_metric_that_has_no_period(): void
    {
        $user = User::factory()->create();

        $this->expectException(UnboundedGoalMetricException::class);

        $this->meter->measureWeek($user, GoalMetric::TodoCompletionRate, $this->week);
    }

    private function activityAt(User $user, string $startedAtUtc, float $distance = 1000.0, int $movingTime = 600): SportActivity
    {
        return SportActivity::factory()->create([
            'user_id' => $user->id,
            'distance' => $distance,
            'moving_time' => $movingTime,
            'started_at' => Carbon::parse($startedAtUtc, 'UTC'),
        ]);
    }
}
