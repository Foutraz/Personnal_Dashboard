<?php

namespace Tests\Feature\Planning;

use Foutraz\Outlook\OutlookManager;
use Functional\Planning\Actions\BuildUserOutlookManager;
use Functional\Planning\Jobs\SyncOutlookEventsJob;
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

class SyncOutlookEventsJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_upserts_events_and_stays_idempotent_across_runs(): void
    {
        $connection = IntegrationConnection::factory()->create([
            'provider' => IntegrationProvider::OutlookCalendar,
            'expires_at' => now()->addHour(),
        ]);

        $this->bindManagerReturningEvents();
        SyncOutlookEventsJob::dispatchSync($connection->id);

        $this->assertSame(2, CalendarEvent::query()->count());

        $this->bindManagerReturningEvents();
        SyncOutlookEventsJob::dispatchSync($connection->id);

        $this->assertSame(2, CalendarEvent::query()->count());
        $this->assertDatabaseHas('calendar_events', [
            'external_id' => 'o-evt-1',
            'user_id' => $connection->user_id,
            'title' => 'Planning sync',
        ]);
    }

    /**
     * Bind a user manager builder serving a single page of two Outlook events.
     */
    private function bindManagerReturningEvents(): void
    {
        $payload = [
            'value' => [
                [
                    'id' => 'o-evt-1',
                    'subject' => 'Planning sync',
                    'bodyPreview' => 'Sync the agendas',
                    'isAllDay' => false,
                    'location' => ['displayName' => 'Teams'],
                    'start' => ['dateTime' => '2026-07-02T09:00:00', 'timeZone' => 'UTC'],
                    'end' => ['dateTime' => '2026-07-02T09:30:00', 'timeZone' => 'UTC'],
                    'webLink' => 'https://outlook.office.com/event?id=o-evt-1',
                ],
                [
                    'id' => 'o-evt-2',
                    'subject' => 'Holiday',
                    'isAllDay' => true,
                    'start' => ['dateTime' => '2026-07-04T00:00:00', 'timeZone' => 'UTC'],
                    'end' => ['dateTime' => '2026-07-05T00:00:00', 'timeZone' => 'UTC'],
                ],
            ],
        ];

        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode($payload)),
        ]));

        $manager = new OutlookManager(
            'https://graph.microsoft.com/v1.0',
            'token',
            'client-id',
            'client-secret',
            'https://localhost/planning/outlook/callback',
            'common',
            new Client(['handler' => $handler, 'http_errors' => false]),
        );

        $this->app->bind(BuildUserOutlookManager::class, fn (): BuildUserOutlookManager => new class($manager) extends BuildUserOutlookManager
        {
            public function __construct(private OutlookManager $stub)
            {
                parent::__construct($stub);
            }

            public function __invoke(IntegrationConnection $connection): OutlookManager
            {
                return $this->stub;
            }
        });
    }
}
