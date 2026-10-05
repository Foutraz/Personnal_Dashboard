<?php

namespace Technical\Osdd\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class WithinScale implements ValidationRule
{
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

        if (is_float($number)) {
            return round($number, $this->scale) === $number;
        }

        return $this->isStringWithinScale(trim($number));
    }

    private function isStringWithinScale(string $number): bool
    {
        if (preg_match('/^[+-]?\d*\.?(\d*)$/', $number, $matches) === 1) {
            return strlen(rtrim($matches[1], '0')) <= $this->scale;
        }

        return $this->isWithinScale((float) $number);
    }
}
