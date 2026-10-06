<?php

namespace Functional\Goals\Services\Dto;

use Carbon\CarbonInterface;

final readonly class MeasurementPeriod
{
    public function __construct(
        public CarbonInterface $startsAt,
        public CarbonInterface $endsAt,
        public ?CarbonInterface $recordedBefore = null,
    ) {}
}
