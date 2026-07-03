<?php

namespace Functional\Gamification\Jobs;

use Functional\Gamification\Actions\AwardXp;
use Functional\Gamification\Contracts\XpRule;
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
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->userId)];
    }

    /**
     * Run every tagged xp rule for the user and write the awards to the ledger.
     */
    public function handle(AwardXp $awardXp): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $awards = collect(app()->tagged('gamification.xp_rules'))
            ->flatMap(fn (XpRule $rule) => $rule->awards($user, $this->since));

        $awardXp->handle($user, $awards);
    }
}
