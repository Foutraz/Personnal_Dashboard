<?php

namespace Tests\Feature\Sport;

use Foutraz\Strava\StravaManager;
use Functional\Sport\Jobs\SyncStravaAthleteJob;
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

class StravaCallbackTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_strava_connection_and_dispatches_the_athlete_sync(): void
    {
        Queue::fake();

        $this->bindManagerReturning([
            'access_token' => 'fresh-access-token',
            'refresh_token' => 'fresh-refresh-token',
            'expires_at' => now()->addHours(6)->timestamp,
            'expires_in' => 21600,
            'token_type' => 'Bearer',
            'athlete' => ['id' => 99887766],
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->withSession(['strava_state' => 'valid-state'])
            ->get('/sport/strava/callback?code=valid-code&state=valid-state');

        $response->assertRedirect(route('sport'));

        $connection = IntegrationConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', IntegrationProvider::Strava)
            ->firstOrFail();

        $this->assertSame('fresh-access-token', $connection->access_token);
        $this->assertSame('99887766', $connection->external_id);

        Queue::assertPushed(SyncStravaAthleteJob::class, fn (SyncStravaAthleteJob $job): bool => $job->connectionId === $connection->id);
    }

    #[Test]
    public function it_rejects_a_denied_callback(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->get('/sport/strava/callback?error=access_denied');

        $response->assertServerError();
        $this->assertDatabaseCount('integration_connections', 0);
    }

    #[Test]
    public function it_rejects_a_callback_with_a_missing_state(): void
    {
        $this->bindManagerReturning([
            'access_token' => 'fresh-access-token',
            'refresh_token' => 'fresh-refresh-token',
            'expires_at' => now()->addHours(6)->timestamp,
            'expires_in' => 21600,
            'token_type' => 'Bearer',
            'athlete' => ['id' => 99887766],
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->get('/sport/strava/callback?code=valid-code&state=valid-state');

        $response->assertServerError();
        $this->assertDatabaseCount('integration_connections', 0);
    }

    #[Test]
    public function it_rejects_a_callback_with_a_mismatched_state(): void
    {
        $this->bindManagerReturning([
            'access_token' => 'fresh-access-token',
            'refresh_token' => 'fresh-refresh-token',
            'expires_at' => now()->addHours(6)->timestamp,
            'expires_in' => 21600,
            'token_type' => 'Bearer',
            'athlete' => ['id' => 99887766],
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->withSession(['strava_state' => 'expected-state'])
            ->get('/sport/strava/callback?code=valid-code&state=forged-state');

        $response->assertServerError();
        $this->assertDatabaseCount('integration_connections', 0);
    }

    #[Test]
    public function it_forces_the_approval_prompt_when_redirecting_to_strava(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->get('/sport/strava/connect');

        $response->assertRedirectContains('approval_prompt=force');
    }

    /**
     * Bind a Strava manager whose token endpoint returns the given payload.
     *
     * @param  array<string, mixed>  $token
     */
    private function bindManagerReturning(array $token): void
    {
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode($token)),
        ]));

        $this->app->bind(StravaManager::class, fn (): StravaManager => new StravaManager(
            'https://www.strava.com/api/v3',
            'token',
            'client-id',
            'client-secret',
            'https://localhost/sport/strava/callback',
            new Client(['handler' => $handler, 'http_errors' => false]),
        ));
    }
}
