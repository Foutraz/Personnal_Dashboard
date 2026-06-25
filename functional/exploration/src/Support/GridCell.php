<?php

namespace Functional\Exploration\Support;

class GridCell
{
    public function __construct(
        public int $x,
        public int $y,
        public float $cellSize,
    ) {}

    /**
     * Build the grid cell that contains the given coordinate for a cell size.
     */
    public static function fromCoordinate(float $lat, float $lng, float $cellSize): self
    {
        return new self(
            (int) floor($lng / $cellSize),
            (int) floor($lat / $cellSize),
            $cellSize,
        );
    }

    /**
     * Get the deterministic key identifying the cell.
     */
    public function key(): string
    {
        return $this->x.':'.$this->y;
    }

    /**
     * Get the latitude of the cell centroid.
     */
    public function centerLat(): float
    {
        return ($this->y + 0.5) * $this->cellSize;
    }

    /**
     * Get the longitude of the cell centroid.
     */
    public function centerLng(): float
    {
        return ($this->x + 0.5) * $this->cellSize;
    }

    /**
     * Approximate the ground area covered by the cell in square kilometers.
     */
    public function areaKm2(): float
    {
        $latKm = $this->cellSize * 111.32;
        $lngKm = $this->cellSize * 111.32 * cos(deg2rad($this->centerLat()));

        return $latKm * $lngKm;
    }
}
