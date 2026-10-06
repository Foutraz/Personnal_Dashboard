<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\InvalidStateException;
use Technical\WebAuthentication\Exceptions\SocialStateMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return;
            }

            return route('login');
        });

    })
    ->withExceptions(function (Exceptions $exceptions) {

        $exceptions->map(fn (InvalidStateException $exception): SocialStateMismatchException => new SocialStateMismatchException($exception));

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

        });

    })
    ->create();
