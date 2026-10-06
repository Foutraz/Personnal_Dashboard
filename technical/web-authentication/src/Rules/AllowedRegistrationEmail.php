<?php

namespace Technical\WebAuthentication\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Technical\WebAuthentication\Services\RegistrationAllowList;

class AllowedRegistrationEmail implements ValidationRule
{
    public function __construct(private readonly RegistrationAllowList $allowList) {}

    /**
     * Fail with one generic message that never tells whether the email already has an account.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->allowList->permits($value)) {
            $fail('web-authentication::auth.registration_not_allowed')->translate();
        }
    }
}
