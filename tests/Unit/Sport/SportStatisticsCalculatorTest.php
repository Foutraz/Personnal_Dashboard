<?php

namespace Tests\Unit\Sport;

use Functional\Sport\Enums\EvolutionPeriod;
use Functional\Sport\Enums\SportType;
use Functional\Sport\Models\SportActivity;
use Functional\Sport\Services\SportStatisticsCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SportStatisticsCalculatorTest extends TestCase
{
    private SportStatisticsCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new SportStatisticsCalculator;
    }

    #[Test]
    public function it_sums_distance_elevation_and_moving_time(): void
    {
        $activities = $this->activities();

        $this->assertSame(15000.0, $this->calculator->totalDistance($activities));
        $this->assertSame(450.0, $this->calculator->totalElevation($activities));
        $this->assertSame(5400, $this->calculator->totalMovingTime($activities));
    }

    #[Test]
    public function it_counts_activities_by_type(): void
    {
        $counts = $this->calculator->countByType($this->activities());

        $this->assertSame(2, $counts[SportType::Run->label()]);
        $this->assertSame(1, $counts[SportType::Ride->label()]);
    }

    #[Test]
    public function it_aggregates_distance_evolution_by_month(): void
    {
        $evolution = $this->calculator->evolutionByPeriod($this->activities(), EvolutionPeriod::Monthly);

        $this->assertSame(8000.0, $evolution['2026-01']);
        $this->assertSame(7000.0, $evolution['2026-02']);
    }

    #[Test]
    public function it_computes_average_pace_in_seconds_per_kilometer(): void
    {
        $activities = $this->activities();

        $this->assertSame(5400 / 15.0, $this->calculator->averagePace($activities));
    }

    #[Test]
    public function it_returns_zero_pace_without_distance(): void
    {
        $this->assertSame(0.0, $this->calculator->averagePace(new Collection));
    }

    /**
     * Build a hand-crafted collection of activities.
     *
     * @return Collection<int, SportActivity>
     */
    private function activities(): Collection
    {
        return new Collection([
            $this->makeActivity(SportType::Run, 5000.0, 100.0, 1800, '2026-01-05'),
            $this->makeActivity(SportType::Run, 3000.0, 50.0, 1200, '2026-01-20'),
            $this->makeActivity(SportType::Ride, 7000.0, 300.0, 2400, '2026-02-10'),
        ]);
    }

    private function makeActivity(SportType $type, float $distance, float $elevation, int $movingTime, string $startedAt): SportActivity
    {
        return (new SportActivity)->forceFill([
            'sport_type' => $type,
            'distance' => $distance,
            'total_elevation_gain' => $elevation,
            'moving_time' => $movingTime,
            'started_at' => Carbon::parse($startedAt),
        ]);
    }
}
