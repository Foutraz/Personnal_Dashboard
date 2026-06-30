<?php

namespace Tests\Feature\Finance;

use DateTimeImmutable;
use Foutraz\GoCardlessBank\Dto\BankTransaction;
use Foutraz\GoCardlessBank\Dto\Requisition;
use Foutraz\GoCardlessBank\Dto\TokenResponse;
use Foutraz\GoCardlessBank\GoCardlessManager;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoCardlessSdkTest extends TestCase
{
    #[Test]
    public function it_parses_new_token_response(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                'access' => 'a',
                'access_expires' => 86400,
                'refresh' => 'r',
                'refresh_expires' => 2592000,
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $guzzleClient = new Client(['handler' => $handlerStack, 'http_errors' => false]);

        $manager = new GoCardlessManager(
            endpoint: 'https://bankaccountdata.gocardless.com',
            secretId: 'secret-id',
            secretKey: 'secret-key',
            redirectUri: 'https://example.com/callback',
            client: $guzzleClient,
        );

        $tokenResponse = $manager->auth()->newToken();

        $this->assertInstanceOf(TokenResponse::class, $tokenResponse);
        $this->assertSame('a', $tokenResponse->accessToken);
        $this->assertGreaterThan(time(), $tokenResponse->expiresAt);
    }

    #[Test]
    public function it_parses_create_requisition_response(): void
    {
        $mock = new MockHandler([
            new Response(201, [], (string) json_encode([
                'id' => 'req-1',
                'link' => 'https://bank/link',
                'status' => 'CR',
                'accounts' => [],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $guzzleClient = new Client(['handler' => $handlerStack, 'http_errors' => false]);

        $manager = new GoCardlessManager(
            endpoint: 'https://bankaccountdata.gocardless.com',
            secretId: 'secret-id',
            secretKey: 'secret-key',
            redirectUri: 'https://example.com/callback',
            client: $guzzleClient,
        );

        $requisition = $manager->requisitions()->createRequisition('INST', 'https://cb', null);

        $this->assertInstanceOf(Requisition::class, $requisition);
        $this->assertSame('req-1', $requisition->id);
        $this->assertSame('https://bank/link', $requisition->link);
        $this->assertSame('CR', $requisition->status);
    }

    #[Test]
    public function it_parses_transactions_response(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                'transactions' => [
                    'booked' => [
                        [
                            'transactionId' => 't1',
                            'bookingDate' => '2026-06-01',
                            'transactionAmount' => [
                                'amount' => '-12.50',
                                'currency' => 'EUR',
                            ],
                            'remittanceInformationUnstructured' => 'Coffee',
                        ],
                    ],
                ],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $guzzleClient = new Client(['handler' => $handlerStack, 'http_errors' => false]);

        $manager = new GoCardlessManager(
            endpoint: 'https://bankaccountdata.gocardless.com',
            secretId: 'secret-id',
            secretKey: 'secret-key',
            redirectUri: 'https://example.com/callback',
            client: $guzzleClient,
        );

        $transactions = $manager->accounts()->transactions('acc-1');

        $this->assertCount(1, $transactions);
        $this->assertInstanceOf(BankTransaction::class, $transactions[0]);
        $this->assertSame('t1', $transactions[0]->externalId);
        $this->assertEqualsWithDelta(-12.5, $transactions[0]->amount, 0.001);
        $this->assertSame('EUR', $transactions[0]->currency);
        $this->assertInstanceOf(DateTimeImmutable::class, $transactions[0]->bookedAt);
        $this->assertSame('Coffee', $transactions[0]->description);
    }
}
