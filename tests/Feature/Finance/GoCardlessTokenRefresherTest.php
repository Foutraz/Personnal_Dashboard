<?php

namespace Tests\Feature\Finance;

use Foutraz\GoCardlessBank\GoCardlessManager;
use Functional\Finance\Services\GoCardlessTokenRefresher;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoCardlessTokenRefresherTest extends TestCase
{
    #[Test]
    public function it_returns_rotated_credentials_from_a_refresh_token(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                'access' => 'new-access-token',
                'access_expires' => 86400,
                'refresh' => 'new-refresh-token',
                'refresh_expires' => 2592000,
            ])),
        ]);

        $handler = HandlerStack::create($mock);
        $manager = new GoCardlessManager(
            endpoint: 'https://bankaccountdata.gocardless.com',
            secretId: 'secret-id',
            secretKey: 'secret-key',
            redirectUri: 'https://example.com/callback',
            client: new Client(['handler' => $handler, 'http_errors' => false]),
        );

        $result = (new GoCardlessTokenRefresher($manager))->refresh('old-refresh-token');

        $this->assertSame('new-access-token', $result['access_token']);
        $this->assertSame('new-refresh-token', $result['refresh_token']);
        $this->assertIsInt($result['expires_at']);
        $this->assertGreaterThan(time(), $result['expires_at']);
    }
}
