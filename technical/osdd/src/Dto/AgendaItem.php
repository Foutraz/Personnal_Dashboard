<?php

namespace Technical\Osdd\Dto;

use Illuminate\Support\Carbon;

final readonly class AgendaItem
{
    /**
     * Describe a single time-anchored item aggregated onto the dashboard agenda.
     */
    public function __construct(
        public string $id,
        public string $source,
        public string $title,
        public Carbon $startsAt,
        public ?Carbon $endsAt,
        public bool $allDay,
        public string $accent,
        public ?string $location = null,
        public ?string $link = null,
        public ?string $amount = null,
        public ?string $href = null,
    ) {}
}
