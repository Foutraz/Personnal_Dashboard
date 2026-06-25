<?php

namespace Functional\Exploration\Services;

use Functional\Exploration\Services\Dto\CoverageResult;
use Functional\Exploration\Support\BoundingBox;
use Functional\Exploration\Support\GridCell;
use Illuminate\Support\Collection;

class CoverageCalculator
{
    /**
     * The grid cell size in degrees.
     */
    private float $cellSize;

    public function __construct(?float $cellSize = null)
    {
        $this->cellSize = $cellSize ?? (float) config('exploration.grid.cell_size', 0.01);
    }

    /**
     * Snap a collection of decoded routes to the grid returning the explored coverage.
     *
     * @param  Collection<int, array<int, array{lat: float, lng: float}>>  $routes
     */
    public function coverage(Collection $routes): CoverageResult
    {
        /** @var array<string, GridCell> $cells */
        $cells = [];

        foreach ($routes as $points) {
            foreach ($points as $point) {
                $cell = GridCell::fromCoordinate($point['lat'], $point['lng'], $this->cellSize);
                $cells[$cell->key()] = $cell;
            }
        }

        $area = array_reduce(
            $cells,
            fn (float $carry, GridCell $cell): float => $carry + $cell->areaKm2(),
            0.0,
        );

        return new CoverageResult($cells, $area);
    }

    /**
     * Compute the exploration percentage of explored cells within a region bounding box.
     *
     * @param  array<string, GridCell>  $cells
     */
    public function explorationPercentage(array $cells, BoundingBox $region): float
    {
        $totalCells = $this->totalCellsWithin($region);

        if ($totalCells <= 0) {
            return 0.0;
        }

        $exploredWithin = 0;

        foreach ($cells as $cell) {
            if ($region->contains($cell->centerLat(), $cell->centerLng())) {
                $exploredWithin++;
            }
        }

        return round(($exploredWithin / $totalCells) * 100, 2);
    }

    /**
     * Count the total grid cells covering the given region bounding box.
     */
    public function totalCellsWithin(BoundingBox $region): int
    {
        $minCell = GridCell::fromCoordinate($region->minLat, $region->minLng, $this->cellSize);
        $maxCell = GridCell::fromCoordinate($region->maxLat, $region->maxLng, $this->cellSize);

        $columns = $maxCell->x - $minCell->x + 1;
        $rows = $maxCell->y - $minCell->y + 1;

        return $columns * $rows;
    }
}
