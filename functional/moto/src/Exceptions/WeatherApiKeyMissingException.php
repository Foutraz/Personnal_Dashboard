<?php

namespace Functional\Moto\Exceptions;

use RuntimeException;

class WeatherApiKeyMissingException extends RuntimeException
{
    /**
     * Build the exception raised when the OpenWeatherMap API key is not configured.
     */
    public static function create(): self
    {
        return new self('The OpenWeatherMap API key is not configured.');
    }
}
