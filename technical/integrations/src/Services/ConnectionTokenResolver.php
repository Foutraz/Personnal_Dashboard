<?php

namespace Technical\Integrations\Services;

use Illuminate\Support\Carbon;
use Technical\Integrations\Contracts\RefreshesAccessToken;
use Technical\Integrations\Exceptions\TokenRefreshFailedException;
use Technical\Integrations\Models\IntegrationConnection;

class ConnectionTokenResolver
{
    public function __construct(private RefreshesAccessToken $tokenRefresher) {}

    /**
     * Return a non-expired access token, refreshing and persisting it when needed.
     *
     * @throws TokenRefreshFailedException
     */
    public function freshAccessToken(IntegrationConnection $connection): string
    {
        if (! $connection->isExpired()) {
            return $connection->access_token;
        }

        if ($connection->refresh_token === null) {
            throw new TokenRefreshFailedException('The connection has no refresh token.');
        }

        $rotated = $this->tokenRefresher->refresh($connection->refresh_token);

        if (! $this->hasValidShape($rotated)) {
            throw new TokenRefreshFailedException('The token refresh response is malformed.');
        }

        $connection->forceFill([
            'access_token' => $rotated['access_token'],
            'refresh_token' => $rotated['refresh_token'],
            'expires_at' => Carbon::createFromTimestamp($rotated['expires_at']),
        ])->save();

        return $connection->access_token;
    }

    /**
     * Determine whether the refresh response carries the expected shape.
     *
     * @param  array{access_token?: mixed, refresh_token?: mixed, expires_at?: mixed}  $rotated
     */
    private function hasValidShape(array $rotated): bool
    {
        return isset($rotated['access_token'], $rotated['refresh_token'], $rotated['expires_at'])
            && is_string($rotated['access_token'])
            && is_string($rotated['refresh_token'])
            && is_int($rotated['expires_at']);
    }
}
