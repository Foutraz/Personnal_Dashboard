<?php

namespace Functional\Finance\Jobs;

use Functional\Finance\Actions\BuildUserGoCardlessManager;
use Functional\Finance\Actions\UpsertBankTransaction;
use Functional\Finance\Events\BankTransactionsSynced;
use Functional\Finance\Models\BankAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SyncBankTransactionsJob implements ShouldQueue
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

    public function __construct(public string $bankAccountId) {}

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->bankAccountId)];
    }

    /**
     * Sync the bank account's transactions into local storage.
     */
    public function handle(BuildUserGoCardlessManager $buildManager, UpsertBankTransaction $upsert): void
    {
        $account = BankAccount::query()->find($this->bankAccountId);

        if ($account === null) {
            return;
        }

        $connection = $account->connection;

        if ($connection === null) {
            return;
        }

        $manager = $buildManager($connection);

        $transactions = $manager->accounts()->transactions($account->external_id);

        foreach ($transactions as $transaction) {
            $upsert(
                account: $account,
                externalId: $transaction->externalId,
                amount: $transaction->amount,
                currency: $transaction->currency,
                bookedAt: Carbon::instance($transaction->bookedAt),
                description: $transaction->description,
                counterparty: $transaction->counterparty,
            );
        }

        BankTransactionsSynced::dispatch($account->user_id);
    }
}
