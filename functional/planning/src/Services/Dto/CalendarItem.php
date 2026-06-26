<?php

namespace Functional\Planning\Services\Dto;

use Illuminate\Support\Carbon;

class CalendarItem
{
    /**
     * Build a normalized calendar item aggregated from multiple sources.
     */
    public function __construct(
        public string $id,
        public CalendarItemSource $source,
        public string $title,
        public Carbon $startsAt,
        public ?Carbon $endsAt,
        public bool $allDay,
        public ?string $location,
        public ?string $link,
        public ?string $amount,
    ) {}
}
