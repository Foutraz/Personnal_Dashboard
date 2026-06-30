<?php

namespace Functional\Finance\Actions;

use Foutraz\GoCardlessBank\Dto\TokenResponse;
use Illuminate\Support\Carbon;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;

class FindOrCreateGoCardlessConnection
{
    /**
     * Persist or update the GoCardless connection of a user from an exchanged token.
     *
     * @param  array<string, mixed>  $meta
     */
    public function __invoke(string $userId, TokenResponse $token, array $meta): IntegrationConnection
    {
        return IntegrationConnection::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'provider' => IntegrationProvider::GoCardless,
            ],
            [
                'access_token' => $token->accessToken,
                'refresh_token' => $token->refreshToken,
                'expires_at' => Carbon::createFromTimestamp($token->expiresAt),
                'meta' => $meta,
            ]
        );
    }
}
