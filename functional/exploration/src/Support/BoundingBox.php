<?php

namespace Functional\Exploration\Support;

class BoundingBox
{
    public function __construct(
        public float $minLat,
        public float $minLng,
        public float $maxLat,
        public float $maxLng,
    ) {}

    /**
     * Build a bounding box from a region preset array.
     *
     * @param  array{min_lat: float|int|string, max_lat: float|int|string, min_lng: float|int|string, max_lng: float|int|string}  $region
     */
    public static function fromRegion(array $region): self
    {
        return new self(
            (float) $region['min_lat'],
            (float) $region['min_lng'],
            (float) $region['max_lat'],
            (float) $region['max_lng'],
        );
    }

    /**
     * Determine whether the given coordinate falls inside the bounding box.
     */
    public function contains(float $lat, float $lng): bool
    {
        return $lat >= $this->minLat
            && $lat <= $this->maxLat
            && $lng >= $this->minLng
            && $lng <= $this->maxLng;
    }
}
