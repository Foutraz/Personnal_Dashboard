<?php

namespace Functional\Planning\Actions;

use Illuminate\Support\Carbon;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;

class FindOrCreateCalendarConnection
{
    /**
     * Persist or update the calendar connection of a user from exchanged token credentials.
     *
     * @param  array{access_token: string, refresh_token: ?string, expires_at: int, scope: ?string}  $token
     * @param  array<int, string>  $scopes
     */
    public function __invoke(string $userId, IntegrationProvider $provider, array $token, array $scopes): IntegrationConnection
    {
        return IntegrationConnection::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'provider' => $provider,
            ],
            [
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'],
                'expires_at' => Carbon::createFromTimestamp($token['expires_at']),
                'scopes' => $scopes,
            ]
        );
    }
}
