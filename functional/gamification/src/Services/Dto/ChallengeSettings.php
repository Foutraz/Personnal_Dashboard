<?php

namespace Functional\Gamification\Services\Dto;

use Functional\Gamification\Exceptions\InvalidChallengeConfigException;

final readonly class ChallengeSettings
{
    private const CONFIG_PREFIX = 'gamification.challenges';

    private const MAX_XP_REWARD = 65535;

    private const MAX_HISTORY_WEEKS = 52;

    private const MAX_CLOSING_GRACE_HOURS = 168;

    public function __construct(
        public int $historyWeeks,
        public int $minActiveWeeks,
        public float $stretchRatio,
        public int $closingGraceHours,
        public int $xpReward,
    ) {}

    /**
     * @throws InvalidChallengeConfigException
     */
    public static function fromConfig(): self
    {
        $historyWeeks = self::integerBetween('history_weeks', 1, self::MAX_HISTORY_WEEKS);

        return new self(
            historyWeeks: $historyWeeks,
            minActiveWeeks: self::integerBetween('min_active_weeks', 1, $historyWeeks),
            stretchRatio: self::stretchRatio(),
            closingGraceHours: self::integerBetween('closing_grace_hours', 0, self::MAX_CLOSING_GRACE_HOURS),
            xpReward: self::integerBetween('xp_reward', 1, self::MAX_XP_REWARD),
        );
    }

    private static function integerBetween(string $setting, int $minimum, int $maximum): int
    {
        $configPath = self::CONFIG_PREFIX.".{$setting}";
        $configured = config($configPath);

        if (! is_int($configured) || $configured < $minimum || $configured > $maximum) {
            throw InvalidChallengeConfigException::integerBetween($configPath, $configured, $minimum, $maximum);
        }

        return $configured;
    }

    private static function stretchRatio(): float
    {
        $configPath = self::CONFIG_PREFIX.'.stretch_ratio';
        $configured = config($configPath);

        if ((! is_int($configured) && ! is_float($configured)) || $configured < 0) {
            throw InvalidChallengeConfigException::numberAtLeast($configPath, $configured, 0);
        }

        return (float) $configured;
    }
}
