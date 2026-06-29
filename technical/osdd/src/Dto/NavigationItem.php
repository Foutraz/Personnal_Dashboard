<?php

namespace Technical\Osdd\Dto;

final readonly class NavigationItem
{
    /**
     * Describe a single sidebar navigation entry.
     */
    public function __construct(
        public string $label,
        public string $route,
        public string $icon,
        public int $order,
    ) {}
}
