<?php

namespace Functional\Finance\Validation;

use Technical\Osdd\Rules\IsoDatetime;
use Technical\Osdd\Rules\WithinScale;

final class InvestmentTransactionRules
{
    public const MAX_QUANTITY = 1000000000;

    public const MAX_UNIT_PRICE = 10000000;

    public const QUANTITY_SCALE = 8;

    public const UNIT_PRICE_SCALE = 8;

    public const EARLIEST_EXECUTION = '1970-01-01';

    /**
     * @return list<string|WithinScale>
     */
    public static function quantity(): array
    {
        return [
            'numeric',
            new WithinScale(self::QUANTITY_SCALE),
            'gt:0',
            sprintf('max:%d', self::MAX_QUANTITY),
        ];
    }

    /**
     * @return list<string|WithinScale>
     */
    public static function unitPrice(): array
    {
        return [
            'numeric',
            new WithinScale(self::UNIT_PRICE_SCALE),
            'gt:0',
            sprintf('max:%d', self::MAX_UNIT_PRICE),
        ];
    }

    /**
     * @return list<string|IsoDatetime>
     */
    public static function executedAt(): array
    {
        return [new IsoDatetime, 'date', sprintf('after:%s', self::EARLIEST_EXECUTION), 'before_or_equal:now'];
    }
}
