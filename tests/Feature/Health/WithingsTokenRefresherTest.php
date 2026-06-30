<?php

namespace Tests\Feature\Health;

use Foutraz\Withings\WithingsManager;
use Functional\Health\Services\WithingsTokenRefresher;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WithingsTokenRefresherTest extends TestCase
{
    #[Test]
    public function it_returns_rotated_credentials_from_a_refresh_token(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                'status' => 0,
                'body' => [
                    'access_token' => 'new-access-token',
                    'refresh_token' => 'new-refresh-token',
                    'expires_in' => 3600,
                    'userid' => 42,
                    'token_type' => 'Bearer',
                ],
            ])),
        ]);

        $handler = HandlerStack::create($mock);
        $manager = new WithingsManager(
            endpoint: 'https://wbsapi.withings.net',
            apiToken: '',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            redirectUri: 'https://example.com/callback',
            client: new Client(['handler' => $handler, 'http_errors' => false]),
        );

        $result = (new WithingsTokenRefresher($manager))->refresh('old-refresh-token');

        $this->assertSame('new-access-token', $result['access_token']);
        $this->assertSame('new-refresh-token', $result['refresh_token']);
        $this->assertIsInt($result['expires_at']);
        $this->assertGreaterThan(time(), $result['expires_at']);
    }
}
