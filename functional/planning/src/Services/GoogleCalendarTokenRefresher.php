<?php

namespace Functional\Planning\Services;

use Foutraz\GoogleCalendar\GoogleCalendarManager;
use Technical\Integrations\Contracts\RefreshesAccessToken;

class GoogleCalendarTokenRefresher implements RefreshesAccessToken
{
    public function __construct(private GoogleCalendarManager $manager) {}

    /**
     * Exchange a Google refresh token for a rotated set of credentials.
     *
     * @return array{access_token: string, refresh_token: string, expires_at: int}
     */
    public function refresh(string $refreshToken): array
    {
        $token = $this->manager->auth()->refreshToken($refreshToken);

        return [
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken ?? $refreshToken,
            'expires_at' => $token->expiresAt,
        ];
    }
}
