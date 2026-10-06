<?php

namespace Technical\Application\RateLimiting;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthenticationAttemptsLimiter
{
    public const NAME = 'authentication-attempts';

    public const ATTEMPTS_PER_MINUTE = 5;

    public const ATTEMPTS_PER_HOUR_PER_EMAIL = 30;

    /**
     * Get the route middleware applying this limiter.
     */
    public static function middleware(): string
    {
        return 'throttle:'.self::NAME;
    }

    /**
     * Limit the attempts per client address and email, and the hourly attempts per email from any address.
     *
     * @return list<Limit>
     */
    public function __invoke(Request $request): array
    {
        $email = $request->input('email');
        $normalizedEmail = is_string($email) ? Str::lower(trim($email)) : '';
        $limits = [Limit::perMinute(self::ATTEMPTS_PER_MINUTE)->by($request->ip().'|'.$normalizedEmail)];

        if ($normalizedEmail === '') {
            return $limits;
        }

        $limits[] = Limit::perHour(self::ATTEMPTS_PER_HOUR_PER_EMAIL)->by('email|'.$normalizedEmail);

        return $limits;
    }
}
