<?php

namespace Technical\WebAuthentication\Exceptions;

class UnverifiedAccountLinkException extends SocialSignInRefusedException
{
    /**
     * Create an exception raised when a Google identity would be linked to an account whose email is unverified.
     */
    public function __construct(public readonly string $accountId)
    {
        parent::__construct(sprintf('Refused to link a Google identity to the unverified account %s.', $accountId));
    }
}
