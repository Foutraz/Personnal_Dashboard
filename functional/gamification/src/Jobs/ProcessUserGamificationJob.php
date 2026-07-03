<?php

namespace Functional\Gamification\Jobs;

use Functional\Gamification\Actions\RunUserGamification;
use Functional\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ProcessUserGamificationJob implements ShouldQueue
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
        public string $userId,
        public ?Carbon $since = null,
    ) {}

    /**
     * Build the shared cache lock key guarding a user's gamification recomputation.
     */
    public static function overlapKey(string $userId): string
    {
        return 'laravel-queue-overlap:'.$userId;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->userId))->shared()->releaseAfter(60)->expireAfter(600)];
    }

    /**
     * Run the gamification rules for the user over the requested window.
     */
    public function handle(RunUserGamification $runUserGamification): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $runUserGamification->handle($user, $this->since);
    }
}
