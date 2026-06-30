<?php

namespace Functional\Finance\Actions;

use Functional\Finance\Models\BankAccount;
use Functional\Finance\Models\BankTransaction;
use Illuminate\Support\Carbon;

class UpsertBankTransaction
{
    /**
     * Idempotently persist a bank transaction for the given account.
     *
     * @param  array<string, mixed>|null  $raw
     */
    public function __invoke(
        BankAccount $account,
        string $externalId,
        float $amount,
        string $currency,
        Carbon $bookedAt,
        ?string $description = null,
        ?string $counterparty = null,
        ?array $raw = null,
    ): BankTransaction {
        return BankTransaction::query()->updateOrCreate(
            [
                'bank_account_id' => $account->id,
                'external_id' => $externalId,
            ],
            [
                'user_id' => $account->user_id,
                'amount' => $amount,
                'currency' => $currency,
                'booked_at' => $bookedAt,
                'description' => $description,
                'counterparty' => $counterparty,
                'raw' => $raw,
            ]
        );
    }
}
