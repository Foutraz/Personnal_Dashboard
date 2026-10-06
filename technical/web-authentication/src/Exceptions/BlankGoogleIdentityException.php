<?php

namespace Technical\WebAuthentication\Exceptions;

class BlankGoogleIdentityException extends SocialSignInRefusedException
{
    /**
     * Create an exception raised when the signing-in Google identity carries no id.
     */
    public function __construct()
    {
        parent::__construct('Refused a Google identity that carries no id.');
    }
}
