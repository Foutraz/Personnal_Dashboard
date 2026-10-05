<?php

namespace Technical\Application\RateLimiting;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthenticationAttemptsLimiter
{
    public const NAME = 'authentication-attempts';

    public const ATTEMPTS_PER_MINUTE = 5;

    /**
     * Get the route middleware applying this limiter.
     */
    public static function middleware(): string
    {
        return 'throttle:'.self::NAME;
    }

    /**
     * Limit the attempts of one client address on one email address.
     */
    public function __invoke(Request $request): Limit
    {
        $email = $request->input('email');
        $normalizedEmail = is_string($email) ? Str::lower($email) : '';

        return Limit::perMinute(self::ATTEMPTS_PER_MINUTE)->by($request->ip().'|'.$normalizedEmail);
    }
}
