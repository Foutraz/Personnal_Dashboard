<?php

namespace Functional\Health\Jobs;

use Functional\Health\Actions\BuildUserWithingsManager;
use Functional\Health\Actions\UpsertBodyMeasurement;
use Functional\Health\Enums\MeasurementType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Technical\Integrations\Models\IntegrationConnection;

class SyncWithingsMeasurementsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public string $connectionId,
        public ?int $lastUpdate = null,
    ) {}

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->connectionId)];
    }

    /**
     * Sync the connection's body measurements into local storage.
     */
    public function handle(BuildUserWithingsManager $buildManager, UpsertBodyMeasurement $upsert): void
    {
        $connection = IntegrationConnection::query()->find($this->connectionId);

        if ($connection === null) {
            return;
        }

        $manager = $buildManager($connection);

        $userid = (int) $connection->external_id;

        $measurements = $manager->measurements()->getmeas($userid, $this->lastUpdate);

        foreach ($measurements as $measurement) {
            $upsert(
                connection: $connection,
                externalId: (string) $measurement->externalId,
                type: MeasurementType::fromWithings($measurement->type),
                value: $measurement->value,
                measuredAt: Carbon::instance($measurement->measuredAt),
                unit: (string) $measurement->unit,
            );
        }
    }
}
