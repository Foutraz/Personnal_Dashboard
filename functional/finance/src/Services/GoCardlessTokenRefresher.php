<?php

namespace Functional\Finance\Services;

use Foutraz\GoCardlessBank\GoCardlessManager;
use Technical\Integrations\Contracts\RefreshesAccessToken;

class GoCardlessTokenRefresher implements RefreshesAccessToken
{
    public function __construct(private GoCardlessManager $manager) {}

    /**
     * Exchange a GoCardless refresh token for a rotated set of credentials.
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
