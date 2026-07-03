<?php

namespace Tests\Feature\Finance;

use Foutraz\GoCardlessBank\GoCardlessManager;
use Functional\Finance\Actions\BuildUserGoCardlessManager;
use Functional\Finance\Events\BankTransactionsSynced;
use Functional\Finance\Jobs\SyncBankAccountsJob;
use Functional\Finance\Models\BankAccount;
use Functional\Finance\Models\BankTransaction;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class SyncBankAccountsJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_upserts_bank_accounts_and_transactions_and_stays_idempotent_across_runs(): void
    {
        $connection = IntegrationConnection::factory()->create([
            'provider' => IntegrationProvider::GoCardless,
            'expires_at' => now()->addHour(),
            'meta' => [
                'requisition_id' => 'req-1',
                'account_ids' => ['acc-1'],
                'institution_id' => 'SANDBOX_FINECO',
            ],
        ]);

        $this->bindManagerReturningAccountData();
        SyncBankAccountsJob::dispatchSync($connection->id);

        $this->assertSame(1, BankAccount::query()->count());
        $this->assertSame(2, BankTransaction::query()->count());

        $this->bindManagerReturningAccountData();
        SyncBankAccountsJob::dispatchSync($connection->id);

        $this->assertSame(1, BankAccount::query()->count());
        $this->assertSame(2, BankTransaction::query()->count());
        $this->assertDatabaseHas('bank_accounts', [
            'external_id' => 'acc-1',
            'user_id' => $connection->user_id,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'external_id' => 'tx-1',
            'user_id' => $connection->user_id,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'external_id' => 'tx-2',
            'user_id' => $connection->user_id,
        ]);
    }

    #[Test]
    public function it_dispatches_the_transactions_synced_event_after_the_run(): void
    {
        $connection = IntegrationConnection::factory()->create([
            'provider' => IntegrationProvider::GoCardless,
            'expires_at' => now()->addHour(),
            'meta' => [
                'requisition_id' => 'req-1',
                'account_ids' => ['acc-1'],
                'institution_id' => 'SANDBOX_FINECO',
            ],
        ]);

        Event::fake([BankTransactionsSynced::class]);
        $this->bindManagerReturningAccountData();
        SyncBankAccountsJob::dispatchSync($connection->id);

        Event::assertDispatched(fn (BankTransactionsSynced $event): bool => $event->userId === $connection->user_id);
    }

    /**
     * Bind a manager builder returning one account with two booked transactions.
     */
    private function bindManagerReturningAccountData(): void
    {
        $detailsPayload = [
            'account' => [
                'iban' => 'IT60X0542811101000000123456',
                'currency' => 'EUR',
                'name' => 'Test Account',
            ],
        ];

        $balancesPayload = [
            'balances' => [
                [
                    'balanceAmount' => ['amount' => '1234.56', 'currency' => 'EUR'],
                    'balanceType' => 'closingBooked',
                ],
            ],
        ];

        $transactionsPayload = [
            'transactions' => [
                'booked' => [
                    [
                        'transactionId' => 'tx-1',
                        'bookingDate' => '2026-06-01',
                        'transactionAmount' => ['amount' => '-50.00', 'currency' => 'EUR'],
                        'remittanceInformationUnstructured' => 'Coffee shop',
                        'creditorName' => 'Starbucks',
                    ],
                    [
                        'transactionId' => 'tx-2',
                        'bookingDate' => '2026-06-02',
                        'transactionAmount' => ['amount' => '2000.00', 'currency' => 'EUR'],
                        'remittanceInformationUnstructured' => 'Salary',
                        'debtorName' => 'Employer',
                    ],
                ],
            ],
        ];

        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode($detailsPayload)),
            new Response(200, [], (string) json_encode($balancesPayload)),
            new Response(200, [], (string) json_encode($transactionsPayload)),
        ]));

        $manager = new GoCardlessManager(
            endpoint: 'https://bankaccountdata.gocardless.com',
            secretId: 'secret-id',
            secretKey: 'secret-key',
            redirectUri: 'https://example.com/callback',
            client: new Client(['handler' => $handler, 'http_errors' => false]),
        );

        $this->app->bind(BuildUserGoCardlessManager::class, fn (): BuildUserGoCardlessManager => new class($manager) extends BuildUserGoCardlessManager
        {
            public function __construct(private GoCardlessManager $stub)
            {
                parent::__construct($stub);
            }

            public function __invoke(IntegrationConnection $connection): GoCardlessManager
            {
                return $this->stub;
            }
        });
    }
}
