<?php

namespace Functional\Gamification\Enums;

use Illuminate\Support\Number;

enum BadgeUnit: string
{
    private const MAX_DECIMALS = 1;

    case Kilometers = 'km';
    case Count = 'count';
    case Days = 'days';
    case Euros = 'eur';

    /**
     * Format a measure with the locale digit grouping and the unit of the family.
     */
    public function format(float $measure): string
    {
        return __("gamification::badges.units.{$this->value}", ['measure' => $this->formatNumber($measure)]);
    }

    /**
     * Format a bare number with the locale digit grouping and the single decimal of the family units.
     */
    public function formatNumber(float $measure): string
    {
        $formatted = Number::format($measure, maxPrecision: self::MAX_DECIMALS, locale: app()->getLocale());

        return $formatted === false ? (string) $measure : $formatted;
    }
}
