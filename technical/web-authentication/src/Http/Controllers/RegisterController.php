<?php

namespace Technical\WebAuthentication\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Technical\WebAuthentication\Actions\RegisterUser;
use Technical\WebAuthentication\Exceptions\EmailAlreadyTakenException;
use Technical\WebAuthentication\Http\Requests\RegisterWebRequest;

class RegisterController
{
    /**
     * Display the registration form.
     */
    public function show(): View
    {
        return view('web-authentication::register');
    }

    /**
     * Register a new user then authenticate the web session guard.
     *
     * @throws EmailAlreadyTakenException
     */
    public function register(RegisterWebRequest $request, RegisterUser $registerUser): RedirectResponse
    {
        $validated = $request->validated();

        $user = $registerUser([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
