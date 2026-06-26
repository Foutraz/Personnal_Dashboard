<?php

namespace Functional\Moto\ValueObjects;

use Functional\Moto\Enums\RideRating;

class RideCondition
{
    /**
     * @param  array<int, string>  $reasons
     */
    public function __construct(
        public int $score,
        public RideRating $rating,
        public array $reasons,
    ) {}

    /**
     * Get the human-readable label of the underlying rating.
     */
    public function label(): string
    {
        return $this->rating->label();
    }

    /**
     * Represent the ride condition as a serialisable array.
     *
     * @return array{score: int, rating: string, label: string, accent: string, reasons: array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'rating' => $this->rating->value,
            'label' => $this->rating->label(),
            'accent' => $this->rating->accent(),
            'reasons' => $this->reasons,
        ];
    }
}
