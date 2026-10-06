<?php

namespace Technical\Osdd\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class IsoDatetime implements ValidationRule
{
    private const PATTERN = '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?)?\z/i';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail('osdd::validation.iso_datetime')->translate();
        }
    }
}
