<?php

namespace Technical\WebAuthentication\Exceptions;

use Exception;

class SocialProviderDeniedException extends Exception
{
    /**
     * Create an exception raised when the social provider denied the authentication.
     */
    public function __construct(string $message = 'The social authentication was denied.')
    {
        parent::__construct($message);
    }
}
