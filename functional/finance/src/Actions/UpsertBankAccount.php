<?php

namespace Functional\Finance\Actions;

use Functional\Finance\Models\BankAccount;
use Illuminate\Support\Carbon;
use Technical\Integrations\Models\IntegrationConnection;

class UpsertBankAccount
{
    /**
     * Idempotently persist a bank account for the given connection.
     *
     * @param  array<string, mixed>|null  $raw
     */
    public function __invoke(
        IntegrationConnection $connection,
        string $externalId,
        string $currency,
        float $balance,
        ?string $institutionId = null,
        ?string $name = null,
        ?string $iban = null,
        ?Carbon $balanceAt = null,
        ?array $raw = null,
    ): BankAccount {
        return BankAccount::query()->updateOrCreate(
            [
                'integration_connection_id' => $connection->id,
                'external_id' => $externalId,
            ],
            [
                'user_id' => $connection->user_id,
                'institution_id' => $institutionId,
                'name' => $name,
                'iban' => $iban,
                'currency' => $currency,
                'balance' => $balance,
                'balance_at' => $balanceAt,
                'raw' => $raw,
            ]
        );
    }
}
