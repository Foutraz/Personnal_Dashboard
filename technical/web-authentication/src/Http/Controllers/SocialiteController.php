<?php

namespace Technical\WebAuthentication\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Technical\WebAuthentication\Actions\FindOrCreateSocialUser;
use Technical\WebAuthentication\Exceptions\SocialProviderDeniedException;

class SocialiteController
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the Google callback and authenticate the web session guard.
     *
     * @throws SocialProviderDeniedException
     */
    public function callback(Request $request, FindOrCreateSocialUser $finder): RedirectResponse
    {
        if ($request->has('error')) {
            throw new SocialProviderDeniedException;
        }

        /** @var SocialUser $socialUser */
        $socialUser = Socialite::driver('google')->user();

        $user = $finder($socialUser);

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}
