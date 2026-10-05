<?php

namespace Technical\WebAuthentication\Exceptions;

use Exception;
use Illuminate\Http\RedirectResponse;

abstract class SocialSignInRefusedException extends Exception
{
    /**
     * Send the refused visitor back to the login screen with a message that does not reveal why.
     */
    public function render(): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'email' => __('web-authentication::auth.social_link_refused'),
        ]);
    }
}
