<?php

namespace Functional\Exploration\Jobs;

use Functional\Exploration\Actions\RebuildUserCoverage;
use Functional\Exploration\Events\CoverageRebuilt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class RebuildCoverageJob implements ShouldQueue
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

    public function __construct(public string $userId) {}

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->userId)];
    }

    /**
     * Rebuild the explored cells coverage for the user.
     */
    public function handle(RebuildUserCoverage $rebuild): void
    {
        $rebuild->handle($this->userId);

        CoverageRebuilt::dispatch($this->userId);
    }
}
