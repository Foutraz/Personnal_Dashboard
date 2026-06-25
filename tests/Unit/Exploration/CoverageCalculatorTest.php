<?php

namespace Tests\Unit\Exploration;

use Functional\Exploration\Services\CoverageCalculator;
use Functional\Exploration\Support\BoundingBox;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoverageCalculatorTest extends TestCase
{
    private CoverageCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new CoverageCalculator(0.01);
    }

    #[Test]
    public function it_counts_distinct_cells_across_routes(): void
    {
        $routes = collect([
            [
                ['lat' => 48.8566, 'lng' => 2.3522],
                ['lat' => 48.8666, 'lng' => 2.3622],
            ],
            [
                ['lat' => 45.7640, 'lng' => 4.8357],
            ],
        ]);

        $result = $this->calculator->coverage($routes);

        $this->assertSame(3, $result->cellCount());
        $this->assertGreaterThan(0.0, $result->areaKm2);
    }

    #[Test]
    public function it_deduplicates_overlapping_points_in_the_same_cell(): void
    {
        $routes = collect([
            [
                ['lat' => 48.8566, 'lng' => 2.3522],
                ['lat' => 48.8567, 'lng' => 2.3523],
                ['lat' => 48.8568, 'lng' => 2.3524],
            ],
        ]);

        $result = $this->calculator->coverage($routes);

        $this->assertSame(1, $result->cellCount());
    }

    #[Test]
    public function it_computes_the_exploration_percentage_within_a_bounding_box(): void
    {
        $routes = collect([
            [
                ['lat' => 10.005, 'lng' => 10.005],
                ['lat' => 10.015, 'lng' => 10.015],
            ],
        ]);

        $cells = $this->calculator->coverage($routes)->cells;

        $region = new BoundingBox(10.0, 10.0, 10.0999, 10.0999);

        $percentage = $this->calculator->explorationPercentage($cells, $region);

        $this->assertSame(100, $this->calculator->totalCellsWithin($region));
        $this->assertSame(2.0, $percentage);
    }

    #[Test]
    public function it_excludes_cells_outside_the_region(): void
    {
        $routes = collect([
            [
                ['lat' => 10.005, 'lng' => 10.005],
                ['lat' => 60.005, 'lng' => 60.005],
            ],
        ]);

        $cells = $this->calculator->coverage($routes)->cells;

        $region = new BoundingBox(10.0, 10.0, 10.0999, 10.0999);

        $exploredWithin = 0;

        foreach ($cells as $cell) {
            if ($region->contains($cell->centerLat(), $cell->centerLng())) {
                $exploredWithin++;
            }
        }

        $this->assertSame(1, $exploredWithin);
    }
}
