<?php

namespace Technical\WebAuthentication\Exceptions;

class DeletedAccountSignInException extends SocialSignInRefusedException
{
    /**
     * Create an exception raised when the account matching a Google sign-in has been soft-deleted.
     */
    public function __construct(public readonly string $accountId)
    {
        parent::__construct(sprintf('Refused the Google sign-in because the matching account %s is soft-deleted.', $accountId));
    }
}
