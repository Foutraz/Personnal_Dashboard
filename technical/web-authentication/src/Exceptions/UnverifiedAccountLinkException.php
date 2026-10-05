<?php

namespace Technical\WebAuthentication\Exceptions;

class UnverifiedAccountLinkException extends SocialSignInRefusedException
{
    public function __construct(public readonly string $accountId)
    {
        parent::__construct(sprintf('Refused to link a Google identity to the unverified account %s.', $accountId));
    }
}
