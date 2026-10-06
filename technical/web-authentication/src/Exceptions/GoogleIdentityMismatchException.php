<?php

namespace Technical\WebAuthentication\Exceptions;

class GoogleIdentityMismatchException extends SocialSignInRefusedException
{
    /**
     * Create an exception raised when a Google identity differs from the one already linked to the account.
     */
    public function __construct(public readonly string $accountId)
    {
        parent::__construct(sprintf('Refused a Google identity that differs from the one linked to account %s.', $accountId));
    }
}
