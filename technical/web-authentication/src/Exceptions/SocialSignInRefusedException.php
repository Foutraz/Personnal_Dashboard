<?php

namespace Technical\WebAuthentication\Exceptions;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

abstract class SocialSignInRefusedException extends Exception
{
    /**
     * Log the refusal reason with the account id or the Google id, never the email, instead of reporting an error.
     */
    public function report(): void
    {
        Log::info($this->getMessage(), ['refusal' => static::class]);
    }

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
