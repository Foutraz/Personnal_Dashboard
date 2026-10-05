<?php

namespace Functional\Goals\Services\Dto;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Grammar;

final readonly class MetricAggregate
{
    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(
        public string $model,
        public string $dateColumn,
        public ?string $summedColumn,
        public float $divisor,
        public ?string $recordedColumn = null,
    ) {}

    public function expression(Grammar $grammar): string
    {
        if ($this->summedColumn === null) {
            return 'count(*)';
        }

        return "sum({$grammar->wrap($this->summedColumn)})";
    }
}
