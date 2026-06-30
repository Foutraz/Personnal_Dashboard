<?php

namespace Tests\Feature\Finance;

use Foutraz\GoCardlessBank\GoCardlessManager;
use Functional\Finance\Jobs\SyncBankAccountsJob;
use Functional\Users\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class GoCardlessCallbackTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_gocardless_connection_and_dispatches_the_bank_account_sync(): void
    {
        Queue::fake();

        $this->bindManagerReturning([
            new Response(200, [], (string) json_encode([
                'access' => 'fresh-access-token',
                'access_expires' => 86400,
                'refresh' => 'fresh-refresh-token',
                'refresh_expires' => 2592000,
            ])),
            new Response(200, [], (string) json_encode([
                'id' => 'req-1',
                'link' => 'https://bank/link',
                'status' => 'LN',
                'accounts' => ['acc-1'],
            ])),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->withSession(['gocardless_requisition' => 'req-1'])
            ->get('/finance/gocardless/callback?ref=req-1');

        $response->assertRedirect(route('finance'));

        $connection = IntegrationConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', IntegrationProvider::GoCardless)
            ->firstOrFail();

        $this->assertSame('fresh-access-token', $connection->access_token);
        $this->assertContains('acc-1', $connection->meta['account_ids']);

        Queue::assertPushed(SyncBankAccountsJob::class, fn (SyncBankAccountsJob $job): bool => $job->connectionId === $connection->id);
    }

    #[Test]
    public function it_rejects_a_callback_with_a_mismatched_ref(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->withSession(['gocardless_requisition' => 'req-1'])
            ->get('/finance/gocardless/callback?ref=other-ref');

        $response->assertServerError();
        $this->assertDatabaseCount('integration_connections', 0);
    }

    #[Test]
    public function it_rejects_a_callback_with_a_missing_session_ref(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->get('/finance/gocardless/callback?ref=req-1');

        $response->assertServerError();
        $this->assertDatabaseCount('integration_connections', 0);
    }

    #[Test]
    public function it_rejects_a_callback_when_requisition_is_not_linked(): void
    {
        $this->bindManagerReturning([
            new Response(200, [], (string) json_encode([
                'access' => 'fresh-access-token',
                'access_expires' => 86400,
                'refresh' => 'fresh-refresh-token',
                'refresh_expires' => 2592000,
            ])),
            new Response(200, [], (string) json_encode([
                'id' => 'req-1',
                'link' => 'https://bank/link',
                'status' => 'CR',
                'accounts' => [],
            ])),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->withSession(['gocardless_requisition' => 'req-1'])
            ->get('/finance/gocardless/callback?ref=req-1');

        $response->assertServerError();
        $this->assertDatabaseCount('integration_connections', 0);
    }

    /**
     * Bind a GoCardless manager whose HTTP calls return the given ordered responses.
     *
     * @param  array<int, Response>  $responses
     */
    private function bindManagerReturning(array $responses): void
    {
        $handler = HandlerStack::create(new MockHandler($responses));
        $mockClient = new Client(['handler' => $handler, 'http_errors' => false]);

        $this->app->bind(GoCardlessManager::class, fn (): GoCardlessManager => new class('https://bankaccountdata.gocardless.com', 'secret-id', 'secret-key', 'https://localhost/finance/gocardless/callback', $mockClient) extends GoCardlessManager
        {
            public function setToken(string $token): static
            {
                return $this;
            }
        });
    }
}
