<?php

namespace Functional\Gamification\Console;

use Functional\Gamification\Jobs\ProcessUserGamificationJob;
use Functional\Gamification\Models\XpEntry;
use Functional\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateGamification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gamification:recalculate {user? : The user id to recalculate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge and replay the gamification ledger, applying the current scoring rules to the full history.';

    /**
     * Purge each targeted ledger inside a transaction and replay the rules synchronously.
     */
    public function handle(): int
    {
        $userId = $this->argument('user');

        $users = $userId !== null
            ? User::query()->whereKey($userId)->cursor()
            : User::query()->cursor();

        foreach ($users as $user) {
            $this->line("Recalculating gamification ledger for user [{$user->id}].");

            DB::transaction(function () use ($user): void {
                XpEntry::query()->where('user_id', $user->id)->delete();

                ProcessUserGamificationJob::dispatchSync($user->id);
            });
        }

        $this->comment('Gamification ledger recalculated.');

        return self::SUCCESS;
    }
}
