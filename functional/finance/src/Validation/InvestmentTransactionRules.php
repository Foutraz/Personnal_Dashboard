<?php

namespace Functional\Finance\Validation;

final class InvestmentTransactionRules
{
    public const MAX_QUANTITY = 1000000000;

    public const MAX_UNIT_PRICE = 10000000;

    public const EARLIEST_EXECUTION = '1970-01-01';

    /**
     * @return list<string>
     */
    public static function quantity(): array
    {
        return ['numeric', 'gt:0', sprintf('max:%d', self::MAX_QUANTITY)];
    }

    /**
     * @return list<string>
     */
    public static function unitPrice(): array
    {
        return ['numeric', 'gt:0', sprintf('max:%d', self::MAX_UNIT_PRICE)];
    }

    /**
     * @return list<string>
     */
    public static function executedAt(): array
    {
        return ['date', sprintf('after:%s', self::EARLIEST_EXECUTION), 'before_or_equal:now'];
    }
}
