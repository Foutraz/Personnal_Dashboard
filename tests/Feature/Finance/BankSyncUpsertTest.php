<?php

namespace Tests\Feature\Finance;

use Functional\Finance\Actions\UpsertBankAccount;
use Functional\Finance\Actions\UpsertBankTransaction;
use Functional\Finance\Models\BankAccount;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class BankSyncUpsertTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_upserts_a_bank_account_and_updates_balance_on_second_call(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create([
            'provider' => IntegrationProvider::GoCardless,
        ]);

        $upsert = app(UpsertBankAccount::class);

        $upsert(
            connection: $connection,
            externalId: 'acc-1',
            currency: 'EUR',
            balance: 1000.00,
            name: 'Checking',
        );

        $upsert(
            connection: $connection,
            externalId: 'acc-1',
            currency: 'EUR',
            balance: 1500.50,
            name: 'Checking',
        );

        $this->assertDatabaseCount('bank_accounts', 1);
        $this->assertDatabaseHas('bank_accounts', [
            'external_id' => 'acc-1',
            'balance' => '1500.50',
        ]);
    }

    #[Test]
    public function it_upserts_a_bank_transaction_and_produces_one_row_on_second_call(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create([
            'provider' => IntegrationProvider::GoCardless,
        ]);

        $account = BankAccount::factory()->for($connection, 'connection')->for($user)->create([
            'external_id' => 'acc-1',
        ]);

        $upsert = app(UpsertBankTransaction::class);

        $upsert(
            account: $account,
            externalId: 't1',
            amount: -12.50,
            currency: 'EUR',
            bookedAt: Carbon::parse('2026-06-01'),
            description: 'Coffee',
        );

        $upsert(
            account: $account,
            externalId: 't1',
            amount: -12.50,
            currency: 'EUR',
            bookedAt: Carbon::parse('2026-06-01'),
            description: 'Coffee updated',
        );

        $this->assertDatabaseCount('bank_transactions', 1);
        $this->assertDatabaseHas('bank_transactions', [
            'external_id' => 't1',
            'description' => 'Coffee updated',
        ]);
    }
}
