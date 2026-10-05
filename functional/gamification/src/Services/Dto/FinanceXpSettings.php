<?php

namespace Functional\Gamification\Services\Dto;

use Functional\Gamification\Exceptions\InvalidFinanceXpConfigException;

final readonly class FinanceXpSettings
{
    private const CONFIG_PREFIX = 'gamification.xp.finance';

    public function __construct(
        public int $positiveSavingsMonth,
        public int $investmentContributionMonth,
        public float $investmentMinimumNetBought,
    ) {}

    /**
     * @throws InvalidFinanceXpConfigException
     */
    public static function fromConfig(): self
    {
        return new self(
            positiveSavingsMonth: self::pointsSetting('positive_savings_month'),
            investmentContributionMonth: self::pointsSetting('investment_contribution_month'),
            investmentMinimumNetBought: self::amountAboveZero('investment_minimum_net_bought'),
        );
    }

    private static function pointsSetting(string $setting): int
    {
        $configPath = self::CONFIG_PREFIX.".{$setting}";
        $configured = config($configPath);

        if (! is_int($configured) || $configured < 0) {
            throw InvalidFinanceXpConfigException::integerAtLeastZero($configPath, $configured);
        }

        return $configured;
    }

    private static function amountAboveZero(string $setting): float
    {
        $configPath = self::CONFIG_PREFIX.".{$setting}";
        $configured = config($configPath);

        if ((! is_int($configured) && ! is_float($configured)) || ! is_finite($configured) || $configured <= 0) {
            throw InvalidFinanceXpConfigException::numberAboveZero($configPath, $configured);
        }

        return (float) $configured;
    }
}
