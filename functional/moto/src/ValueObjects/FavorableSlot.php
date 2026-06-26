<?php

namespace Functional\Moto\ValueObjects;

use DateTimeImmutable;
use Functional\Moto\Enums\RideRating;

class FavorableSlot
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public int $score,
        public RideRating $rating,
    ) {}

    /**
     * Represent the favorable slot as a serialisable array.
     *
     * @return array{starts_at: string, ends_at: string, score: int, rating: string, label: string, accent: string}
     */
    public function toArray(): array
    {
        return [
            'starts_at' => $this->startsAt->format(DateTimeImmutable::ATOM),
            'ends_at' => $this->endsAt->format(DateTimeImmutable::ATOM),
            'score' => $this->score,
            'rating' => $this->rating->value,
            'label' => $this->rating->label(),
            'accent' => $this->rating->accent(),
        ];
    }
}
