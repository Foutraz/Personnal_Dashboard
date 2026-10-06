<?php

namespace Technical\Application\Exceptions;

use UnexpectedValueException;

class InvalidDisplayTimezoneException extends UnexpectedValueException
{
    public static function forValue(mixed $configured): self
    {
        $received = is_string($configured) ? sprintf('"%s"', $configured) : get_debug_type($configured);

        return new self(sprintf(
            'The APP_DISPLAY_TIMEZONE setting (config key app.display_timezone) must be a valid timezone identifier, %s given.',
            $received,
        ));
    }
}
