<?php

namespace Tests\Feature\Health;

use Foutraz\Withings\WithingsManager;
use Functional\Health\Jobs\SyncWithingsUserJob;
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

class WithingsCallbackTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_withings_connection_and_dispatches_the_user_sync(): void
    {
        Queue::fake();

        $this->bindManagerReturning([
            'status' => 0,
            'body' => [
                'access_token' => 'fresh-access-token',
                'refresh_token' => 'fresh-refresh-token',
                'expires_in' => 21600,
                'userid' => 99887766,
                'token_type' => 'Bearer',
            ],
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->withSession(['withings_state' => 'valid-state'])
            ->get('/health/withings/callback?code=valid-code&state=valid-state');

        $response->assertRedirect(route('health'));

        $connection = IntegrationConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', IntegrationProvider::Withings)
            ->firstOrFail();

        $this->assertSame('fresh-access-token', $connection->access_token);
        $this->assertSame('99887766', $connection->external_id);

        Queue::assertPushed(SyncWithingsUserJob::class, fn (SyncWithingsUserJob $job): bool => $job->connectionId === $connection->id);
    }

    #[Test]
    public function it_rejects_a_denied_callback(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->get('/health/withings/callback?error=access_denied');

        $response->assertServerError();
        $this->assertDatabaseCount('integration_connections', 0);
    }

    #[Test]
    public function it_rejects_a_callback_with_a_missing_state(): void
    {
        $this->bindManagerReturning([
            'status' => 0,
            'body' => [
                'access_token' => 'fresh-access-token',
                'refresh_token' => 'fresh-refresh-token',
                'expires_in' => 21600,
                'userid' => 99887766,
                'token_type' => 'Bearer',
            ],
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->get('/health/withings/callback?code=valid-code&state=valid-state');

        $response->assertServerError();
        $this->assertDatabaseCount('integration_connections', 0);
    }

    #[Test]
    public function it_rejects_a_callback_with_a_mismatched_state(): void
    {
        $this->bindManagerReturning([
            'status' => 0,
            'body' => [
                'access_token' => 'fresh-access-token',
                'refresh_token' => 'fresh-refresh-token',
                'expires_in' => 21600,
                'userid' => 99887766,
                'token_type' => 'Bearer',
            ],
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->withSession(['withings_state' => 'expected-state'])
            ->get('/health/withings/callback?code=valid-code&state=forged-state');

        $response->assertServerError();
        $this->assertDatabaseCount('integration_connections', 0);
    }

    /**
     * Bind a Withings manager whose token endpoint returns the given payload.
     *
     * @param  array<string, mixed>  $token
     */
    private function bindManagerReturning(array $token): void
    {
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode($token)),
        ]));

        $this->app->bind(WithingsManager::class, fn (): WithingsManager => new WithingsManager(
            endpoint: 'https://wbsapi.withings.net',
            apiToken: '',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            redirectUri: 'https://localhost/health/withings/callback',
            client: new Client(['handler' => $handler, 'http_errors' => false]),
        ));
    }
}
