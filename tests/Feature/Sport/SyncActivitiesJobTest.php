<?php

namespace Tests\Feature\Sport;

use Foutraz\Strava\StravaManager;
use Functional\Sport\Actions\BuildUserStravaManager;
use Functional\Sport\Jobs\SyncStravaActivitiesJob;
use Functional\Sport\Models\SportActivity;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class SyncActivitiesJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_upserts_activities_and_stays_idempotent_across_runs(): void
    {
        $connection = IntegrationConnection::factory()->create([
            'expires_at' => now()->addHour(),
        ]);

        $this->bindManagerReturningActivities();
        SyncStravaActivitiesJob::dispatchSync($connection->id);

        $this->assertSame(3, SportActivity::query()->count());

        $this->bindManagerReturningActivities();
        SyncStravaActivitiesJob::dispatchSync($connection->id);

        $this->assertSame(3, SportActivity::query()->count());
        $this->assertDatabaseHas('sport_activities', [
            'strava_id' => 1001,
            'user_id' => $connection->user_id,
        ]);
    }

    /**
     * Bind a user manager builder serving a single page of three activities.
     */
    private function bindManagerReturningActivities(): void
    {
        $activities = [
            $this->activity(1001, 'Morning Run', 'Run'),
            $this->activity(1002, 'Evening Ride', 'Ride'),
            $this->activity(1003, 'Pool Swim', 'Swim'),
        ];

        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode($activities)),
        ]));

        $manager = new StravaManager(
            'https://www.strava.com/api/v3',
            'token',
            'client-id',
            'client-secret',
            'https://localhost/sport/strava/callback',
            new Client(['handler' => $handler, 'http_errors' => false]),
        );

        $this->app->bind(BuildUserStravaManager::class, fn (): BuildUserStravaManager => new class($manager) extends BuildUserStravaManager
        {
            public function __construct(private StravaManager $stub)
            {
                parent::__construct($stub);
            }

            public function __invoke(IntegrationConnection $connection): StravaManager
            {
                return $this->stub;
            }
        });
    }

    /**
     * Build a raw Strava activity payload.
     *
     * @return array<string, mixed>
     */
    private function activity(int $id, string $name, string $sportType): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'distance' => 5000.0,
            'moving_time' => 1500,
            'elapsed_time' => 1600,
            'total_elevation_gain' => 80.0,
            'type' => $sportType,
            'sport_type' => $sportType,
            'start_date' => '2026-02-01T08:00:00Z',
        ];
    }
}
