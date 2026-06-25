<?php

namespace Functional\Sport\Jobs;

use Foutraz\Strava\Dto\Activity;
use Functional\Sport\Actions\BuildUserStravaManager;
use Functional\Sport\Actions\UpsertStravaActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Technical\Integrations\Models\IntegrationConnection;

class SyncStravaActivitiesJob implements ShouldQueue
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
        public ?int $after = null,
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
     * Sync the connection's activities into local storage.
     */
    public function handle(BuildUserStravaManager $buildManager, UpsertStravaActivity $upsert): void
    {
        $connection = IntegrationConnection::query()->find($this->connectionId);

        if ($connection === null) {
            return;
        }

        $manager = $buildManager($connection);

        foreach ($manager->activities()->iterate(200, $this->after) as $activity) {
            /** @var Activity $activity */
            $upsert($connection, $activity);
        }
    }
}
