<?php

namespace Technical\Osdd\Dto;

final readonly class DashboardSummary
{
    /**
     * Describe a single module tile rendered on the dashboard.
     *
     * @param  array<int, string>  $secondaryLines
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $accent,
        public string $icon,
        public string $href,
        public int $order,
        public bool $available,
        public string $metricValue,
        public ?string $metricUnit = null,
        public array $secondaryLines = [],
        public ?string $callToAction = null,
    ) {}
}
