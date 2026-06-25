<?php

namespace Technical\WebAuthentication\Exceptions;

use Exception;

class EmailAlreadyTakenException extends Exception
{
    /**
     * Create an exception raised when registering an already used email.
     */
    public function __construct(string $message = 'The provided email address is already taken.')
    {
        parent::__construct($message);
    }
}
