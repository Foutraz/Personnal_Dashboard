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
        return __("gamification::badges.units.{$this->value}", [
            'measure' => Number::format($measure, maxPrecision: self::MAX_DECIMALS, locale: app()->getLocale()),
        ]);
    }
}
