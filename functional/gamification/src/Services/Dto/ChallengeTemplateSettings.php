<?php

namespace Functional\Gamification\Services\Dto;

use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Exceptions\InvalidChallengeConfigException;
use Functional\Gamification\Exceptions\MissingChallengeTemplateConfigException;

final readonly class ChallengeTemplateSettings
{
    public function __construct(
        public float $step,
        public float $floor,
        public float $cap,
    ) {}

    /**
     * @throws MissingChallengeTemplateConfigException|InvalidChallengeConfigException
     */
    public static function fromConfig(ChallengeTemplateKey $template): self
    {
        $configPath = $template->configPath();

        if (! is_array(config($configPath))) {
            throw new MissingChallengeTemplateConfigException($template);
        }

        $step = self::positiveNumber("{$configPath}.step");
        $floor = self::positiveNumber("{$configPath}.floor");
        $cap = self::capAtLeast("{$configPath}.cap", $floor);

        return new self($step, $floor, $cap);
    }

    private static function positiveNumber(string $configPath): float
    {
        $configured = config($configPath);

        if ((! is_int($configured) && ! is_float($configured)) || $configured <= 0) {
            throw InvalidChallengeConfigException::notPositive($configPath, $configured);
        }

        return (float) $configured;
    }

    private static function capAtLeast(string $configPath, float $floor): float
    {
        $configured = config($configPath);

        if ((! is_int($configured) && ! is_float($configured)) || $configured < $floor) {
            throw InvalidChallengeConfigException::belowFloor($configPath, $configured);
        }

        return (float) $configured;
    }
}
