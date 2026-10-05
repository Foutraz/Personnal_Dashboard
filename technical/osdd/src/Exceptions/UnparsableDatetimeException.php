<?php

namespace Technical\Osdd\Exceptions;

use InvalidArgumentException;

class UnparsableDatetimeException extends InvalidArgumentException
{
    public static function forValue(mixed $moment): self
    {
        return new self(sprintf('A value of type %s cannot be read as a datetime.', get_debug_type($moment)));
    }
}
