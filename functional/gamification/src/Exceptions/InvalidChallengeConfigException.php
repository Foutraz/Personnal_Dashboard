<?php

namespace Functional\Gamification\Exceptions;

use RuntimeException;

class InvalidChallengeConfigException extends RuntimeException
{
    private function __construct(public readonly string $configPath, public readonly mixed $configured, string $requirement)
    {
        $given = is_scalar($configured) ? var_export($configured, true) : get_debug_type($configured);

        parent::__construct("The challenge config \"{$configPath}\" must be {$requirement}, {$given} given.");
    }

    public static function notPositive(string $configPath, mixed $configured): self
    {
        return new self($configPath, $configured, 'a number greater than zero');
    }

    public static function belowFloor(string $configPath, mixed $configured): self
    {
        return new self($configPath, $configured, 'a number at least equal to the floor');
    }

    public static function integerAtLeast(string $configPath, mixed $configured, int $minimum): self
    {
        return new self($configPath, $configured, "an integer of at least {$minimum}");
    }

    public static function integerBetween(string $configPath, mixed $configured, int $minimum, int $maximum): self
    {
        return new self($configPath, $configured, "an integer between {$minimum} and {$maximum}");
    }

    public static function numberAtLeast(string $configPath, mixed $configured, int|float $minimum): self
    {
        return new self($configPath, $configured, "a number of at least {$minimum}");
    }
}
