<?php

namespace Technical\Osdd\Rest\Throttle;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

class RestRateLimit
{
    public const NAME = 'rest';

    public static function middleware(): string
    {
        return ThrottleRequests::using(self::NAME);
    }

    public function limitFor(Request $request): Limit
    {
        $userId = $request->user()?->getAuthIdentifier();
        $bucket = $userId === null ? "ip:{$request->ip()}" : "user:{$userId}";

        return Limit::perMinute(config()->integer('osdd.rest.requests_per_minute'))->by($bucket);
    }
}
