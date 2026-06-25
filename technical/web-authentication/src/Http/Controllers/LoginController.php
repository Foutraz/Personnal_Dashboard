<?php

namespace Technical\WebAuthentication\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Technical\WebAuthentication\Http\Requests\LoginWebRequest;

class LoginController
{
    /**
     * Display the login form.
     */
    public function show(): View
    {
        return view('web-authentication::login');
    }

    /**
     * Authenticate the user against the web session guard.
     *
     * @throws ValidationException
     */
    public function login(LoginWebRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $remember = (bool) ($validated['remember'] ?? false);

        if (! Auth::guard('web')->attempt(['email' => $validated['email'], 'password' => $validated['password']], $remember)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Log the user out of the web session guard.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
