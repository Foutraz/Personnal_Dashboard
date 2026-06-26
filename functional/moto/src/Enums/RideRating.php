<?php

namespace Functional\Moto\Enums;

enum RideRating: string
{
    case Excellent = 'excellent';
    case Good = 'good';
    case Average = 'average';
    case Bad = 'bad';
    case Dangerous = 'dangerous';

    /**
     * Get the human-readable label of the riding rating.
     */
    public function label(): string
    {
        return match ($this) {
            self::Excellent => 'Excellent',
            self::Good => 'Bon',
            self::Average => 'Moyen',
            self::Bad => 'Mauvais',
            self::Dangerous => 'Dangereux',
        };
    }

    /**
     * Get the neon accent associated with the riding rating.
     */
    public function accent(): string
    {
        return match ($this) {
            self::Excellent, self::Good => 'lime',
            self::Average => 'cyan',
            self::Bad, self::Dangerous => 'violet',
        };
    }

    /**
     * Build the riding rating matching the given score and configured label thresholds.
     *
     * @param  array{excellent: int, good: int, average: int, bad: int}  $thresholds
     */
    public static function fromScore(int $score, array $thresholds): self
    {
        return match (true) {
            $score >= $thresholds['excellent'] => self::Excellent,
            $score >= $thresholds['good'] => self::Good,
            $score >= $thresholds['average'] => self::Average,
            $score >= $thresholds['bad'] => self::Bad,
            default => self::Dangerous,
        };
    }
}
