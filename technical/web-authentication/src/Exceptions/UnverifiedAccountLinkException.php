<?php

namespace Technical\WebAuthentication\Exceptions;

use Exception;
use Illuminate\Http\RedirectResponse;

class UnverifiedAccountLinkException extends Exception
{
    public function __construct(public readonly string $accountId)
    {
        parent::__construct(sprintf('Refused to link a Google identity to the unverified account %s.', $accountId));
    }

    public function render(): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'email' => __('web-authentication::auth.social_link_refused'),
        ]);
    }
}
