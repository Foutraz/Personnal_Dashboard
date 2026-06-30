<?php

namespace Functional\Health\Actions;

use Foutraz\Withings\Dto\TokenResponse;
use Illuminate\Support\Carbon;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;

class FindOrCreateWithingsConnection
{
    /**
     * Persist or update the Withings connection of a user from an exchanged token.
     *
     * @param  array<int, string>  $scopes
     */
    public function __invoke(string $userId, TokenResponse $token, array $scopes): IntegrationConnection
    {
        return IntegrationConnection::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'provider' => IntegrationProvider::Withings,
            ],
            [
                'access_token' => $token->accessToken,
                'refresh_token' => $token->refreshToken,
                'expires_at' => Carbon::createFromTimestamp($token->expiresAt),
                'scopes' => $scopes,
                'external_id' => (string) $token->userid,
            ]
        );
    }
}
