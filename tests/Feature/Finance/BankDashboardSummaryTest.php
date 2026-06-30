<?php

namespace Tests\Feature\Finance;

use Functional\Finance\Dashboard\BankDashboardSummary;
use Functional\Finance\Models\BankAccount;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class BankDashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sums_the_user_bank_account_balances(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create(['provider' => IntegrationProvider::GoCardless]);
        BankAccount::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'balance' => '100.00',
        ]);
        BankAccount::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'balance' => '250.00',
        ]);

        $otherUser = User::factory()->create();
        $otherConnection = IntegrationConnection::factory()->for($otherUser)->create(['provider' => IntegrationProvider::GoCardless]);
        BankAccount::factory()->create([
            'user_id' => $otherUser->id,
            'integration_connection_id' => $otherConnection->id,
            'balance' => '9999.00',
        ]);

        $summary = $this->app->make(BankDashboardSummary::class)->dashboardSummary($user);

        $this->assertSame('bank', $summary->key);
        $this->assertSame(100, $summary->order);
        $this->assertTrue($summary->available);
        $this->assertSame('350', $summary->metricValue);
        $this->assertSame('€', $summary->metricUnit);
        $this->assertNull($summary->callToAction);
    }

    #[Test]
    public function it_is_unavailable_until_gocardless_is_connected(): void
    {
        $user = User::factory()->create();

        $disconnected = $this->app->make(BankDashboardSummary::class)->dashboardSummary($user);
        $this->assertFalse($disconnected->available);
        $this->assertSame('Connecter ma banque', $disconnected->callToAction);
        $this->assertSame('bank', $disconnected->key);

        IntegrationConnection::factory()->for($user)->create(['provider' => IntegrationProvider::GoCardless]);
        $connected = $this->app->make(BankDashboardSummary::class)->dashboardSummary($user);
        $this->assertTrue($connected->available);
    }
}
