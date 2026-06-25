<?php

namespace Functional\Exploration\Services\Dto;

use Functional\Exploration\Support\GridCell;

class CoverageResult
{
    /**
     * @param  array<string, GridCell>  $cells
     */
    public function __construct(
        public array $cells,
        public float $areaKm2,
    ) {}

    /**
     * Count the distinct explored cells.
     */
    public function cellCount(): int
    {
        return count($this->cells);
    }
}
