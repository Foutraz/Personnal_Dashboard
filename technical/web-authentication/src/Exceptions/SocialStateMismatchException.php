<?php

namespace Technical\WebAuthentication\Exceptions;

use Laravel\Socialite\Two\InvalidStateException;

class SocialStateMismatchException extends SocialSignInRefusedException
{
    /**
     * Create an exception raised when the state returned by the social provider does not match the session.
     */
    public function __construct(InvalidStateException $previous)
    {
        parent::__construct('Refused the social callback because its state did not match the session.', 0, $previous);
    }
}
