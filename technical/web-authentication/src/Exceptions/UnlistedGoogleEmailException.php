<?php

namespace Technical\WebAuthentication\Exceptions;

class UnlistedGoogleEmailException extends SocialSignInRefusedException
{
    /**
     * Create an exception raised when a new Google user has an email outside the registration allow-list.
     */
    public function __construct(public readonly string $googleId)
    {
        parent::__construct(sprintf('Refused the Google identity %s because its email is not on the registration allow-list.', $googleId));
    }
}
