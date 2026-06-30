<?php

namespace Tests\Feature\Finance;

use Foutraz\MarketData\MarketDataManager;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarketDataSdkTest extends TestCase
{
    #[Test]
    public function it_returns_prices_for_requested_coin_ids(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'bitcoin' => ['eur' => 58000],
                'ethereum' => ['eur' => 3200],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $guzzleClient = new Client(['handler' => $handlerStack, 'http_errors' => false]);

        $manager = new MarketDataManager(
            endpoint: 'https://api.coingecko.com',
            apiKey: '',
            client: $guzzleClient,
        );

        $prices = $manager->prices()->prices(['bitcoin', 'ethereum'], 'eur');

        $this->assertEqualsWithDelta(58000.0, $prices['bitcoin'], 0.001);
        $this->assertEqualsWithDelta(3200.0, $prices['ethereum'], 0.001);
    }
}
