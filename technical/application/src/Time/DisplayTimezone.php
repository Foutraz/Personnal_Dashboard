<?php

namespace Technical\Application\Time;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Technical\Application\Exceptions\InvalidDisplayTimezoneException;

final class DisplayTimezone
{
    public const INPUT_FORMAT = 'Y-m-d\TH:i';

    /** @throws InvalidDisplayTimezoneException */
    public function name(): string
    {
        $configured = config('app.display_timezone');

        if (! is_string($configured) || ! in_array($configured, timezone_identifiers_list(), true)) {
            throw InvalidDisplayTimezoneException::forValue($configured);
        }

        return $configured;
    }

    /** @throws InvalidDisplayTimezoneException */
    public function toApplicationTime(string $localDateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($localDateTime, $this->name())->setTimezone((string) config('app.timezone'));
    }

    /** @throws InvalidDisplayTimezoneException */
    public function toDisplayTime(CarbonInterface $moment): CarbonImmutable
    {
        return $moment->toImmutable()->setTimezone($this->name());
    }

    /** @throws InvalidDisplayTimezoneException */
    public function inputValue(CarbonInterface $moment): string
    {
        return $this->toDisplayTime($moment)->format(self::INPUT_FORMAT);
    }
}
