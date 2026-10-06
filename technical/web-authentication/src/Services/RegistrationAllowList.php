<?php

namespace Technical\WebAuthentication\Services;

use Illuminate\Support\Str;

class RegistrationAllowList
{
    /**
     * Tell whether the email may open a new account, ignoring case and surrounding spaces.
     */
    public function permits(string $email): bool
    {
        return in_array($this->normalize($email), $this->allowedEmails(), true);
    }

    /**
     * Tell whether at least one email is listed, so the registration is open to someone.
     */
    public function isOpen(): bool
    {
        return $this->allowedEmails() !== [];
    }

    /**
     * @return array<int, string>
     */
    private function allowedEmails(): array
    {
        return collect(explode(',', (string) config('web-authentication.registration.allowed_emails')))
            ->map(fn (string $email): string => $this->normalize($email))
            ->reject(fn (string $email): bool => $email === '')
            ->values()
            ->all();
    }

    private function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }
}
