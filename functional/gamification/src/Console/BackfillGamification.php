<?php

namespace Functional\Gamification\Console;

use Functional\Gamification\Jobs\ProcessUserGamificationJob;
use Functional\Users\Models\User;
use Illuminate\Console\Command;

class BackfillGamification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gamification:backfill {user? : The user id to backfill}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild the gamification ledger from the full data history of the targeted or every user.';

    /**
     * Dispatch a full-history gamification job for the targeted user or every user.
     */
    public function handle(): int
    {
        $userId = $this->argument('user');

        $users = $userId !== null
            ? User::query()->whereKey($userId)->cursor()
            : User::query()->cursor();

        foreach ($users as $user) {
            $this->line("Dispatching gamification backfill for user [{$user->id}].");

            ProcessUserGamificationJob::dispatch($user->id);
        }

        $this->comment('Gamification backfill dispatched.');

        return self::SUCCESS;
    }
}
