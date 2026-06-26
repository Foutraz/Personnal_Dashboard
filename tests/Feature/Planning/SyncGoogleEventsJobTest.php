<?php

namespace Tests\Feature\Planning;

use Foutraz\GoogleCalendar\GoogleCalendarManager;
use Functional\Planning\Actions\BuildUserGoogleCalendarManager;
use Functional\Planning\Jobs\SyncGoogleEventsJob;
use Functional\Planning\Models\CalendarEvent;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class SyncGoogleEventsJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_upserts_events_and_stays_idempotent_across_runs(): void
    {
        $connection = IntegrationConnection::factory()->create([
            'provider' => IntegrationProvider::GoogleCalendar,
            'expires_at' => now()->addHour(),
        ]);

        $this->bindManagerReturningEvents();
        SyncGoogleEventsJob::dispatchSync($connection->id);

        $this->assertSame(2, CalendarEvent::query()->count());

        $this->bindManagerReturningEvents();
        SyncGoogleEventsJob::dispatchSync($connection->id);

        $this->assertSame(2, CalendarEvent::query()->count());
        $this->assertDatabaseHas('calendar_events', [
            'external_id' => 'g-evt-1',
            'user_id' => $connection->user_id,
            'title' => 'Kickoff',
        ]);
    }

    /**
     * Bind a user manager builder serving a single page of two Google events.
     */
    private function bindManagerReturningEvents(): void
    {
        $payload = [
            'items' => [
                [
                    'id' => 'g-evt-1',
                    'status' => 'confirmed',
                    'summary' => 'Kickoff',
                    'start' => ['dateTime' => '2026-07-01T10:00:00+00:00'],
                    'end' => ['dateTime' => '2026-07-01T11:00:00+00:00'],
                ],
                [
                    'id' => 'g-evt-2',
                    'status' => 'confirmed',
                    'summary' => 'Day off',
                    'start' => ['date' => '2026-07-05'],
                    'end' => ['date' => '2026-07-06'],
                ],
            ],
        ];

        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode($payload)),
        ]));

        $manager = new GoogleCalendarManager(
            'https://www.googleapis.com/calendar/v3',
            'token',
            'client-id',
            'client-secret',
            'https://localhost/planning/google/callback',
            new Client(['handler' => $handler, 'http_errors' => false]),
        );

        $this->app->bind(BuildUserGoogleCalendarManager::class, fn (): BuildUserGoogleCalendarManager => new class($manager) extends BuildUserGoogleCalendarManager
        {
            public function __construct(private GoogleCalendarManager $stub)
            {
                parent::__construct($stub);
            }

            public function __invoke(IntegrationConnection $connection): GoogleCalendarManager
            {
                return $this->stub;
            }
        });
    }
}
