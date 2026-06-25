<?php

namespace Technical\Integrations\Contracts;

interface RefreshesAccessToken
{
    /**
     * Exchange a refresh token for a rotated set of credentials.
     *
     * @return array{access_token: string, refresh_token: string, expires_at: int}
     */
    public function refresh(string $refreshToken): array;
}
