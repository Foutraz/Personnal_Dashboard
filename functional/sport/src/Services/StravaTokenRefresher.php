<?php

namespace Functional\Sport\Services;

use Foutraz\Strava\StravaManager;
use Technical\Integrations\Contracts\RefreshesAccessToken;

class StravaTokenRefresher implements RefreshesAccessToken
{
    public function __construct(private StravaManager $manager) {}

    /**
     * Exchange a Strava refresh token for a rotated set of credentials.
     *
     * @return array{access_token: string, refresh_token: string, expires_at: int}
     */
    public function refresh(string $refreshToken): array
    {
        $token = $this->manager->auth()->refreshToken($refreshToken);

        return [
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken,
            'expires_at' => $token->expiresAt,
        ];
    }
}
