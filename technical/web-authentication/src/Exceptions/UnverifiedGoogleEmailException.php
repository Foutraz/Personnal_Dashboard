<?php

namespace Technical\WebAuthentication\Exceptions;

class UnverifiedGoogleEmailException extends SocialSignInRefusedException
{
    /**
     * Create an exception raised when Google did not verify the email of the signing-in identity.
     */
    public function __construct(public readonly string $googleId)
    {
        parent::__construct(sprintf('Refused the Google identity %s because Google did not verify its email.', $googleId));
    }
}
