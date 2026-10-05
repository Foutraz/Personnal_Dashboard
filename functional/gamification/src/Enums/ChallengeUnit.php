<?php

namespace Functional\Gamification\Enums;

use Illuminate\Support\Number;

enum ChallengeUnit: string
{
    case Kilometers = 'km';
    case Meters = 'm';
    case Hours = 'h';
    case Count = 'count';

    public function format(float $measure): string
    {
        return __("gamification::challenges.units.{$this->value}", ['measure' => $this->formatNumber($measure)]);
    }

    public function formatNumber(float $measure): string
    {
        $formatted = Number::format($measure, maxPrecision: $this->maxDecimals(), locale: app()->getLocale());

        return $formatted === false ? (string) $measure : $formatted;
    }

    public function maxDecimals(): int
    {
        return match ($this) {
            self::Kilometers, self::Hours => 1,
            self::Meters, self::Count => 0,
        };
    }
}
