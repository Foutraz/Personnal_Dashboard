<?php

namespace Tests\Feature\Health;

use Foutraz\Withings\WithingsManager;
use Functional\Health\Actions\BuildUserWithingsManager;
use Functional\Health\Jobs\SyncWithingsMeasurementsJob;
use Functional\Health\Models\BodyMeasurement;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class SyncWithingsMeasurementsJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_upserts_measurements_and_stays_idempotent_across_runs(): void
    {
        $connection = IntegrationConnection::factory()->create([
            'provider' => IntegrationProvider::Withings,
            'expires_at' => now()->addHour(),
            'external_id' => '42',
        ]);

        $this->bindManagerReturningMeasurements();
        SyncWithingsMeasurementsJob::dispatchSync($connection->id);

        $this->assertSame(3, BodyMeasurement::query()->count());

        $this->bindManagerReturningMeasurements();
        SyncWithingsMeasurementsJob::dispatchSync($connection->id);

        $this->assertSame(3, BodyMeasurement::query()->count());
        $this->assertDatabaseHas('body_measurements', [
            'external_id' => '111',
            'user_id' => $connection->user_id,
        ]);
    }

    /**
     * Bind a user manager builder serving three body measurements.
     */
    private function bindManagerReturningMeasurements(): void
    {
        $payload = [
            'status' => 0,
            'body' => [
                'measuregrps' => [
                    $this->measureGroup(111, 1700000000, 1, 70500, -3),
                    $this->measureGroup(222, 1700001000, 6, 200, -1),
                    $this->measureGroup(333, 1700002000, 11, 65, 0),
                ],
            ],
        ];

        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode($payload)),
        ]));

        $manager = new WithingsManager(
            endpoint: 'https://wbsapi.withings.net',
            apiToken: 'token',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            redirectUri: 'https://example.com/callback',
            client: new Client(['handler' => $handler, 'http_errors' => false]),
        );

        $this->app->bind(BuildUserWithingsManager::class, fn (): BuildUserWithingsManager => new class($manager) extends BuildUserWithingsManager
        {
            public function __construct(private WithingsManager $stub)
            {
                parent::__construct($stub);
            }

            public function __invoke(IntegrationConnection $connection): WithingsManager
            {
                return $this->stub;
            }
        });
    }

    /**
     * Build a raw Withings measure group payload with a single measure.
     *
     * @return array<string, mixed>
     */
    private function measureGroup(int $grpid, int $date, int $type, int $value, int $unit): array
    {
        return [
            'grpid' => $grpid,
            'date' => $date,
            'measures' => [
                ['type' => $type, 'value' => $value, 'unit' => $unit],
            ],
        ];
    }
}
