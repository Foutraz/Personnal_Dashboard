<?php

namespace Tests\Feature\Planning;

use Foutraz\GoogleCalendar\GoogleCalendarManager;
use Foutraz\Outlook\OutlookManager;
use Functional\Planning\Jobs\SyncGoogleEventsJob;
use Functional\Planning\Jobs\SyncOutlookEventsJob;
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

class CalendarCallbackTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_google_connection_and_dispatches_the_sync(): void
    {
        Queue::fake();
        $this->bindGoogleReturning([
            'access_token' => 'g-access',
            'refresh_token' => 'g-refresh',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->get('/planning/google/callback?code=valid-code');

        $response->assertRedirect(route('planning'));

        $connection = IntegrationConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', IntegrationProvider::GoogleCalendar)
            ->firstOrFail();

        $this->assertSame('g-access', $connection->access_token);

        Queue::assertPushed(SyncGoogleEventsJob::class, fn (SyncGoogleEventsJob $job): bool => $job->connectionId === $connection->id);
    }

    #[Test]
    public function it_creates_an_outlook_connection_and_dispatches_the_sync(): void
    {
        Queue::fake();
        $this->bindOutlookReturning([
            'access_token' => 'o-access',
            'refresh_token' => 'o-refresh',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->get('/planning/outlook/callback?code=valid-code');

        $response->assertRedirect(route('planning'));

        $connection = IntegrationConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', IntegrationProvider::OutlookCalendar)
            ->firstOrFail();

        $this->assertSame('o-access', $connection->access_token);

        Queue::assertPushed(SyncOutlookEventsJob::class, fn (SyncOutlookEventsJob $job): bool => $job->connectionId === $connection->id);
    }

    #[Test]
    public function it_rejects_a_denied_callback(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->get('/planning/google/callback?error=access_denied');

        $response->assertServerError();
        $this->assertDatabaseCount('integration_connections', 0);
    }

    /**
     * Bind a Google manager whose token endpoint returns the given payload.
     *
     * @param  array<string, mixed>  $token
     */
    private function bindGoogleReturning(array $token): void
    {
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode($token)),
        ]));

        $this->app->bind(GoogleCalendarManager::class, fn (): GoogleCalendarManager => new GoogleCalendarManager(
            'https://www.googleapis.com/calendar/v3',
            '',
            'client-id',
            'client-secret',
            'https://localhost/planning/google/callback',
            new Client(['handler' => $handler, 'http_errors' => false]),
        ));
    }

    /**
     * Bind an Outlook manager whose token endpoint returns the given payload.
     *
     * @param  array<string, mixed>  $token
     */
    private function bindOutlookReturning(array $token): void
    {
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode($token)),
        ]));

        $this->app->bind(OutlookManager::class, fn (): OutlookManager => new OutlookManager(
            'https://graph.microsoft.com/v1.0',
            '',
            'client-id',
            'client-secret',
            'https://localhost/planning/outlook/callback',
            'common',
            new Client(['handler' => $handler, 'http_errors' => false]),
        ));
    }
}
