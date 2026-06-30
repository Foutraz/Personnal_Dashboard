<?php

namespace Tests\Feature\Health;

use DateTimeImmutable;
use Foutraz\Withings\Dto\Measurement;
use Foutraz\Withings\Dto\TokenResponse;
use Foutraz\Withings\WithingsManager;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WithingsSdkTest extends TestCase
{
    #[Test]
    public function it_parses_exchange_token_response(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                'status' => 0,
                'body' => [
                    'access_token' => 'access-abc',
                    'refresh_token' => 'refresh-xyz',
                    'expires_in' => 3600,
                    'userid' => 42,
                    'token_type' => 'Bearer',
                ],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $guzzleClient = new Client(['handler' => $handlerStack, 'http_errors' => false]);

        $manager = new WithingsManager(
            endpoint: 'https://wbsapi.withings.net',
            apiToken: '',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            redirectUri: 'https://example.com/callback',
            client: $guzzleClient,
        );

        $tokenResponse = $manager->auth()->exchangeToken('code-123');

        $this->assertInstanceOf(TokenResponse::class, $tokenResponse);
        $this->assertSame('access-abc', $tokenResponse->accessToken);
        $this->assertSame('refresh-xyz', $tokenResponse->refreshToken);
        $this->assertSame(42, $tokenResponse->userid);
    }

    #[Test]
    public function it_parses_getmeas_response_with_correct_value_calculation(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                'status' => 0,
                'body' => [
                    'measuregrps' => [
                        [
                            'grpid' => 111,
                            'date' => 1700000000,
                            'measures' => [
                                ['type' => 1, 'value' => 70500, 'unit' => -3],
                            ],
                        ],
                    ],
                    'more' => 0,
                ],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $guzzleClient = new Client(['handler' => $handlerStack, 'http_errors' => false]);

        $manager = new WithingsManager(
            endpoint: 'https://wbsapi.withings.net',
            apiToken: 'token',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            redirectUri: 'https://example.com/callback',
            client: $guzzleClient,
        );

        $measurements = $manager->measurements()->getmeas(42);

        $this->assertCount(1, $measurements);
        $this->assertInstanceOf(Measurement::class, $measurements[0]);
        $this->assertSame(111, $measurements[0]->externalId);
        $this->assertSame(1, $measurements[0]->type);
        $this->assertEqualsWithDelta(70.5, $measurements[0]->value, 0.001);
        $this->assertInstanceOf(DateTimeImmutable::class, $measurements[0]->measuredAt);
    }
}
