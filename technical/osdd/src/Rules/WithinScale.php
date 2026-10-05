<?php

namespace Technical\Osdd\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class WithinScale implements ValidationRule
{
    private const NUMERIC_WHITESPACE = " \t\n\r\v\f";

    private const NUMBER_PATTERN = '/^[+-]?(\d*)\.?(\d*)(?:[eE]([+-]?\d+))?$/';

    private const EXPONENT_LIMIT = 10000;

    public function __construct(private readonly int $scale) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return;
        }

        if (! $this->isWithinScale($value)) {
            $fail('osdd::validation.within_scale')->translate(['scale' => $this->scale]);
        }
    }

    private function isWithinScale(float|int|string $number): bool
    {
        if (is_int($number)) {
            return true;
        }

        $decimalPlaces = $this->decimalPlaces(
            is_float($number) ? var_export($number, true) : trim($number, self::NUMERIC_WHITESPACE),
        );

        return $decimalPlaces !== null && $decimalPlaces <= $this->scale;
    }

    private function decimalPlaces(string $representation): ?int
    {
        if (preg_match(self::NUMBER_PATTERN, $representation, $parts) !== 1) {
            return null;
        }

        $digits = $parts[1].$parts[2];

        if ($digits === '') {
            return null;
        }

        $significantDigits = rtrim($digits, '0');

        if ($significantDigits === '') {
            return 0;
        }

        $exponent = max(-self::EXPONENT_LIMIT, min(self::EXPONENT_LIMIT, (int) ($parts[3] ?? 0)));
        $strippedZeros = strlen($digits) - strlen($significantDigits);

        return max(0, strlen($parts[2]) - $strippedZeros - $exponent);
    }
}
