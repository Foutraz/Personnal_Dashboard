<?php

namespace Technical\Osdd\Casts;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Technical\Osdd\Exceptions\UnparsableDatetimeException;

/** @implements CastsAttributes<CarbonInterface, mixed> */
final class UtcDatetime implements CastsAttributes, ComparesCastableAttributes
{
    public bool $withoutObjectCaching = true;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonInterface
    {
        if ($value === null) {
            return null;
        }

        return $this->inApplicationTimezone($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->inApplicationTimezone($value)->format($model->getDateFormat());
    }

    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        if ($firstValue === null || $secondValue === null) {
            return $firstValue === $secondValue;
        }

        $format = $model->getDateFormat();

        return $this->inApplicationTimezone($firstValue)->format($format) === $this->inApplicationTimezone($secondValue)->format($format);
    }

    /**
     * @throws UnparsableDatetimeException
     */
    private function inApplicationTimezone(mixed $moment): CarbonInterface
    {
        $timezone = (string) config('app.timezone');

        if ($moment instanceof DateTimeInterface) {
            return Date::instance($moment)->setTimezone($timezone);
        }

        if (is_int($moment) || is_float($moment)) {
            return Date::createFromTimestamp($moment, $timezone);
        }

        if (! is_string($moment)) {
            throw UnparsableDatetimeException::forValue($moment);
        }

        return Date::parse($moment, $timezone)->setTimezone($timezone);
    }
}
