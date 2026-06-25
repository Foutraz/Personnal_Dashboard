<?php

namespace Functional\Exploration\Console;

use Functional\Exploration\Jobs\RebuildCoverageJob;
use Functional\Users\Models\User;
use Illuminate\Console\Command;

class RebuildCoverage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exploration:rebuild-coverage {user? : The user id to rebuild coverage for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild the explored cells coverage from the users sport activities.';

    /**
     * Dispatch a coverage rebuild job for the targeted user or every user.
     */
    public function handle(): int
    {
        $userId = $this->argument('user');

        $users = $userId !== null
            ? User::query()->whereKey($userId)->cursor()
            : User::query()->cursor();

        foreach ($users as $user) {
            $this->line("Dispatching coverage rebuild for user [{$user->id}].");

            RebuildCoverageJob::dispatch($user->id);
        }

        $this->comment('Coverage rebuild dispatched.');

        return self::SUCCESS;
    }
}
