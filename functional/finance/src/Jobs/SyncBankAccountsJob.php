<?php

namespace Functional\Finance\Jobs;

use Functional\Finance\Actions\BuildUserGoCardlessManager;
use Functional\Finance\Actions\UpsertBankAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Technical\Integrations\Models\IntegrationConnection;

class SyncBankAccountsJob implements ShouldQueue
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
     * Sync the connection's bank accounts and dispatch transaction sync for each.
     */
    public function handle(BuildUserGoCardlessManager $buildManager, UpsertBankAccount $upsert): void
    {
        $connection = IntegrationConnection::query()->find($this->connectionId);

        if ($connection === null) {
            return;
        }

        $manager = $buildManager($connection);

        $accountIds = $connection->meta['account_ids'] ?? [];

        foreach ($accountIds as $accountId) {
            $details = $manager->accounts()->details($accountId);
            $balances = $manager->accounts()->balances($accountId);

            $primaryBalance = count($balances) > 0 ? $balances[0] : null;

            $bankAccount = $upsert(
                connection: $connection,
                externalId: $accountId,
                currency: $primaryBalance !== null ? $primaryBalance->currency : ($details['currency'] ?? ''),
                balance: $primaryBalance !== null ? $primaryBalance->amount : 0.0,
                institutionId: $connection->meta['institution_id'] ?? null,
                name: $details['name'] ?? null,
                iban: $details['iban'] ?? null,
                balanceAt: Carbon::now(),
                raw: $details,
            );

            SyncBankTransactionsJob::dispatch($bankAccount->id);
        }
    }
}
