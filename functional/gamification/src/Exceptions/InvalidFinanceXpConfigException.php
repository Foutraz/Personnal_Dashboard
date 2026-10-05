<?php

namespace Functional\Gamification\Exceptions;

use RuntimeException;

class InvalidFinanceXpConfigException extends RuntimeException
{
    private function __construct(public readonly string $configPath, public readonly mixed $configured, string $requirement)
    {
        $given = is_scalar($configured) ? var_export($configured, true) : get_debug_type($configured);

        parent::__construct("The finance xp config \"{$configPath}\" must be {$requirement}, {$given} given.");
    }

    public static function integerAtLeastZero(string $configPath, mixed $configured): self
    {
        return new self($configPath, $configured, 'an integer of at least 0');
    }

    public static function numberAboveZero(string $configPath, mixed $configured): self
    {
        return new self($configPath, $configured, 'a finite number greater than zero');
    }
}
