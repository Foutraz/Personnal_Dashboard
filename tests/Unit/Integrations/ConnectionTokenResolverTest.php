<?php

namespace Tests\Unit\Integrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Contracts\RefreshesAccessToken;
use Technical\Integrations\Exceptions\TokenRefreshFailedException;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\Integrations\Services\ConnectionTokenResolver;
use Tests\TestCase;

class ConnectionTokenResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_the_existing_token_when_not_expired(): void
    {
        $connection = IntegrationConnection::factory()->create([
            'access_token' => 'still-valid-token',
            'expires_at' => now()->addHour(),
        ]);

        $resolver = new ConnectionTokenResolver($this->fakeRefresher([
            'access_token' => 'should-not-be-used',
            'refresh_token' => 'should-not-be-used',
            'expires_at' => now()->addDay()->timestamp,
        ]));

        $this->assertSame('still-valid-token', $resolver->freshAccessToken($connection));
    }

    #[Test]
    public function it_refreshes_and_persists_rotated_tokens_when_expired(): void
    {
        $connection = IntegrationConnection::factory()->expired()->create([
            'access_token' => 'expired-token',
            'refresh_token' => 'old-refresh-token',
        ]);

        $newExpiry = now()->addHour()->timestamp;

        $resolver = new ConnectionTokenResolver($this->fakeRefresher([
            'access_token' => 'rotated-access-token',
            'refresh_token' => 'rotated-refresh-token',
            'expires_at' => $newExpiry,
        ]));

        $token = $resolver->freshAccessToken($connection);

        $this->assertSame('rotated-access-token', $token);

        $persisted = $connection->fresh();
        $this->assertSame('rotated-access-token', $persisted->access_token);
        $this->assertSame('rotated-refresh-token', $persisted->refresh_token);
        $this->assertSame($newExpiry, $persisted->expires_at->timestamp);
    }

    #[Test]
    public function it_throws_when_the_refresh_response_is_malformed(): void
    {
        $connection = IntegrationConnection::factory()->expired()->create([
            'refresh_token' => 'old-refresh-token',
        ]);

        $resolver = new ConnectionTokenResolver($this->fakeRefresher([
            'access_token' => 'rotated-access-token',
        ]));

        $this->expectException(TokenRefreshFailedException::class);

        $resolver->freshAccessToken($connection);
    }

    #[Test]
    public function it_throws_when_the_connection_has_no_refresh_token(): void
    {
        $connection = IntegrationConnection::factory()->expired()->create([
            'refresh_token' => null,
        ]);

        $resolver = new ConnectionTokenResolver($this->fakeRefresher([]));

        $this->expectException(TokenRefreshFailedException::class);

        $resolver->freshAccessToken($connection);
    }

    /**
     * Build a fake token refresher returning a fixed response.
     *
     * @param  array<string, mixed>  $response
     */
    private function fakeRefresher(array $response): RefreshesAccessToken
    {
        return new class($response) implements RefreshesAccessToken
        {
            /**
             * @param  array<string, mixed>  $response
             */
            public function __construct(private array $response) {}

            /**
             * @return array{access_token: string, refresh_token: string, expires_at: int}
             */
            public function refresh(string $refreshToken): array
            {
                /** @var array{access_token: string, refresh_token: string, expires_at: int} */
                return $this->response;
            }
        };
    }
}
