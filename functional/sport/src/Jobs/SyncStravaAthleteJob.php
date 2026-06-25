<?php

namespace Functional\Sport\Jobs;

use Functional\Sport\Actions\BuildUserStravaManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Technical\Integrations\Models\IntegrationConnection;

class SyncStravaAthleteJob implements ShouldQueue
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

    public function __construct(public string $connectionId) {}

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
     * Refresh the athlete profile then dispatch the activities sync.
     */
    public function handle(BuildUserStravaManager $buildManager): void
    {
        $connection = IntegrationConnection::query()->find($this->connectionId);

        if ($connection === null) {
            return;
        }

        $athlete = $buildManager($connection)->athletes()->me();

        $connection->forceFill([
            'external_id' => (string) $athlete->id,
            'meta' => [
                'firstname' => $athlete->firstname,
                'lastname' => $athlete->lastname,
                'profile_image_url' => $athlete->profileImageUrl,
                'city' => $athlete->city,
                'country' => $athlete->country,
            ],
        ])->save();

        SyncStravaActivitiesJob::dispatch($connection->id);
    }
}
