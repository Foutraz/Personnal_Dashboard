<?php

namespace Tests\Unit\Moto;

use Functional\Moto\Models\MotoRide;
use Functional\Moto\Services\RidingStatsCalculator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RidingStatsCalculatorTest extends TestCase
{
    /**
     * @param  array<int, array{distance: float, duration: int}>  $rows
     * @return Collection<int, MotoRide>
     */
    private function rides(array $rows): Collection
    {
        return collect($rows)->map(function (array $row): MotoRide {
            $ride = new MotoRide;
            $ride->distance = (string) $row['distance'];
            $ride->duration = $row['duration'];

            return $ride;
        });
    }

    #[Test]
    public function it_sums_distance_and_duration(): void
    {
        $calculator = new RidingStatsCalculator;
        $rides = $this->rides([
            ['distance' => 120.0, 'duration' => 3600],
            ['distance' => 80.0, 'duration' => 1800],
        ]);

        $this->assertSame(200.0, $calculator->totalDistance($rides));
        $this->assertSame(5400, $calculator->totalDuration($rides));
        $this->assertSame(2, $calculator->rideCount($rides));
    }

    #[Test]
    public function it_computes_average_distance_and_speed(): void
    {
        $calculator = new RidingStatsCalculator;
        $rides = $this->rides([
            ['distance' => 100.0, 'duration' => 3600],
            ['distance' => 50.0, 'duration' => 3600],
        ]);

        $this->assertSame(75.0, $calculator->averageDistance($rides));
        $this->assertSame(75.0, $calculator->averageSpeed($rides));
    }

    #[Test]
    public function it_returns_zero_for_empty_rides(): void
    {
        $calculator = new RidingStatsCalculator;
        $rides = $this->rides([]);

        $this->assertSame(0.0, $calculator->averageDistance($rides));
        $this->assertSame(0.0, $calculator->averageSpeed($rides));
    }
}
