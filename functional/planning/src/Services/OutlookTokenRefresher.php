<?php

namespace Functional\Planning\Services;

use Foutraz\Outlook\OutlookManager;
use Technical\Integrations\Contracts\RefreshesAccessToken;

class OutlookTokenRefresher implements RefreshesAccessToken
{
    public function __construct(private OutlookManager $manager) {}

    /**
     * Exchange an Outlook refresh token for a rotated set of credentials.
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
