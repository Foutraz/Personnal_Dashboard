<?php

namespace Technical\WebAuthentication\Exceptions;

class SocialProviderDeniedException extends SocialSignInRefusedException
{
    /**
     * Create an exception raised when the social provider denied the authentication.
     */
    public function __construct(string $message = 'The social authentication was denied.')
    {
        parent::__construct($message);
    }
}
