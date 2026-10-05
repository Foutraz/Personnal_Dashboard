<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Services\Dto\GamificationWeek;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Gamification\Services\WeeklyMetricMeter;
use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Services\Dto\MeasurementPeriod;
use Functional\Goals\Services\GoalMetricAggregator;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WeeklyMetricMeterRecordingCutoffTest extends TestCase
{
    use RefreshDatabase;

    private const GRACE_HOURS = 48;

    private GamificationWeek $week;

    protected function setUp(): void
    {
        parent::setUp();

        $this->week = $this->app->make(GamificationCalendar::class)->weekOf(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
    }

    /**
     * @return array<string, array{GoalMetric}>
     */
    public static function aggregatedMetrics(): array
    {
        $cases = [];

        foreach (GoalMetric::cases() as $metric) {
            if ($metric->isPeriodBound() && $metric !== GoalMetric::FinanceInvestedCapital) {
                $cases[$metric->value] = [$metric];
            }
        }

        return $cases;
    }

    private function aggregatorCapturingPeriods(): GoalMetricAggregator
    {
        return new class extends GoalMetricAggregator
        {
            /** @var array<array-key, MeasurementPeriod> */
            public array $periods = [];

            public function totalsPerPeriod(GoalMetric $metric, string $userId, array $periods): array
            {
                $this->periods = $periods;

                return array_fill(0, count($periods), 0.0);
            }
        };
    }

    #[Test]
    #[DataProvider('aggregatedMetrics')]
    public function it_hands_the_closing_instant_to_the_aggregator_for_the_measure_of_every_metric(GoalMetric $metric): void
    {
        $aggregator = $this->aggregatorCapturingPeriods();
        $closesAt = $this->week->closesAt(self::GRACE_HOURS);

        (new WeeklyMetricMeter($aggregator))->measure(User::factory()->create(), $metric, $this->week->startsAt, $this->week->endsAt, $closesAt);

        $this->assertCount(1, $aggregator->periods);
        $this->assertSame($closesAt->toIso8601String(), $aggregator->periods[0]->recordedBefore?->toIso8601String());
    }

    #[Test]
    #[DataProvider('aggregatedMetrics')]
    public function it_hands_the_closing_instant_of_each_week_to_the_aggregator_for_the_history_of_every_metric(GoalMetric $metric): void
    {
        $aggregator = $this->aggregatorCapturingPeriods();

        (new WeeklyMetricMeter($aggregator))->history(User::factory()->create(), $metric, [$this->week], self::GRACE_HOURS);

        $this->assertCount(1, $aggregator->periods);
        $this->assertSame($this->week->closesAt(self::GRACE_HOURS)->toIso8601String(), $aggregator->periods[0]->recordedBefore?->toIso8601String());
    }

    #[Test]
    #[DataProvider('aggregatedMetrics')]
    public function it_cuts_a_metric_on_the_recording_instant_exactly_when_it_is_self_reported(GoalMetric $metric): void
    {
        $user = User::factory()->create();
        DB::enableQueryLog();

        $this->app->make(WeeklyMetricMeter::class)->measure($user, $metric, $this->week->startsAt, $this->week->endsAt, $this->week->closesAt(self::GRACE_HOURS));

        $aggregateQuery = collect(DB::getQueryLog())->pluck('query')->sole();
        $this->assertSame($metric->isSelfReported(), str_contains($aggregateQuery, 'recorded_at'));
    }
}
