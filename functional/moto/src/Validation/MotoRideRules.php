<?php

namespace Functional\Moto\Validation;

use Carbon\CarbonInterface;
use Technical\Osdd\Rules\IsoDatetime;
use Technical\Osdd\Rules\WithinScale;

final class MotoRideRules
{
    public const MAX_DISTANCE_KM = 2000;

    public const DISTANCE_SCALE = 2;

    public const MIN_DURATION_MINUTES = 1;

    public const MAX_DURATION_MINUTES = 1440;

    public const EARLIEST_START = '1970-01-01';

    /**
     * @return list<string|IsoDatetime>
     */
    public static function startedAt(): array
    {
        return [new IsoDatetime, 'date', sprintf('after:%s', self::EARLIEST_START), 'before_or_equal:now'];
    }

    /**
     * @return list<string|WithinScale>
     */
    public static function distance(): array
    {
        return [
            'numeric',
            new WithinScale(self::DISTANCE_SCALE),
            'gt:0',
            sprintf('max:%d', self::MAX_DISTANCE_KM),
        ];
    }

    /**
     * @return list<string>
     */
    public static function durationInSeconds(): array
    {
        return [
            'integer',
            sprintf('min:%d', self::MIN_DURATION_MINUTES * CarbonInterface::SECONDS_PER_MINUTE),
            sprintf('max:%d', self::MAX_DURATION_MINUTES * CarbonInterface::SECONDS_PER_MINUTE),
        ];
    }

    /**
     * @return list<string>
     */
    public static function durationInMinutes(): array
    {
        return [
            'numeric',
            sprintf('min:%d', self::MIN_DURATION_MINUTES),
            sprintf('max:%d', self::MAX_DURATION_MINUTES),
        ];
    }
}
