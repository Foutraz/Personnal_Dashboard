<?php

namespace Tests\Feature\WebAuthentication\Concerns;

trait OpensRegistration
{
    protected function allowRegistrationFor(string ...$emails): void
    {
        config(['web-authentication.registration.allowed_emails' => implode(',', $emails)]);
    }
}
