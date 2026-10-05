<?php

namespace Tests\Feature\Finance;

use Functional\Finance\Enums\AssetType;
use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Livewire\PortfolioOverview;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortfolioOverviewComponentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_position_owned_by_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(PortfolioOverview::class)
            ->set('newSymbol', 'vwce')
            ->set('newName', 'Vanguard All-World')
            ->set('newType', AssetType::Etf->value)
            ->set('newCurrentPrice', '110')
            ->call('createPosition');

        $this->assertDatabaseHas('positions', [
            'asset_symbol' => 'VWCE',
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_records_a_transaction_and_recomputes_the_position_holdings(): void
    {
        $user = User::factory()->create();
        $position = Position::factory()->create([
            'user_id' => $user->id,
            'quantity' => 0,
            'average_buy_price' => 0,
        ]);

        Livewire::actingAs($user)
            ->test(PortfolioOverview::class)
            ->call('startTransaction', $position->id)
            ->set('txType', TransactionType::Buy->value)
            ->set('txQuantity', '10')
            ->set('txUnitPrice', '150')
            ->call('recordTransaction');

        $position->refresh();

        $this->assertDatabaseHas('investment_transactions', [
            'position_id' => $position->id,
            'user_id' => $user->id,
        ]);
        $this->assertEquals(10.0, (float) $position->quantity);
        $this->assertEquals(150.0, (float) $position->average_buy_price);
    }

    #[Test]
    public function it_deletes_a_position_owned_by_the_user(): void
    {
        $user = User::factory()->create();
        $position = Position::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(PortfolioOverview::class)
            ->call('deletePosition', $position->id);

        $this->assertSoftDeleted('positions', ['id' => $position->id]);
    }

    #[Test]
    public function it_does_not_delete_another_users_position(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $position = Position::factory()->create(['user_id' => $other->id]);

        Livewire::actingAs($user)
            ->test(PortfolioOverview::class)
            ->call('deletePosition', $position->id);

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'deleted_at' => null]);
    }

    private function recordTransactionWith(User $user, Position $position, array $form): Testable
    {
        $component = Livewire::actingAs($user)
            ->test(PortfolioOverview::class)
            ->call('startTransaction', $position->id)
            ->set('txType', TransactionType::Buy->value);

        foreach (['txQuantity' => '2', 'txUnitPrice' => '150.5', ...$form] as $property => $value) {
            $component->set($property, $value);
        }

        return $component->call('recordTransaction');
    }

    #[Test]
    public function it_rejects_a_zero_transaction_quantity(): void
    {
        $user = User::factory()->create();
        $position = Position::factory()->for($user)->create();

        $this->recordTransactionWith($user, $position, ['txQuantity' => '0'])
            ->assertHasErrors(['txQuantity' => 'gt']);

        $this->assertSame(0, InvestmentTransaction::query()->count());
    }

    #[Test]
    public function it_rejects_a_zero_transaction_unit_price(): void
    {
        $user = User::factory()->create();
        $position = Position::factory()->for($user)->create();

        $this->recordTransactionWith($user, $position, ['txUnitPrice' => '0'])
            ->assertHasErrors(['txUnitPrice' => 'gt']);

        $this->assertSame(0, InvestmentTransaction::query()->count());
    }

    #[Test]
    public function it_rejects_a_transaction_quantity_above_the_maximum(): void
    {
        $user = User::factory()->create();
        $position = Position::factory()->for($user)->create();

        $this->recordTransactionWith($user, $position, ['txQuantity' => '1000000001'])
            ->assertHasErrors(['txQuantity' => 'max']);

        $this->assertSame(0, InvestmentTransaction::query()->count());
    }

    #[Test]
    public function it_rejects_a_transaction_unit_price_above_the_maximum(): void
    {
        $user = User::factory()->create();
        $position = Position::factory()->for($user)->create();

        $this->recordTransactionWith($user, $position, ['txUnitPrice' => '10000000.01'])
            ->assertHasErrors(['txUnitPrice' => 'max']);

        $this->assertSame(0, InvestmentTransaction::query()->count());
    }

    #[Test]
    public function it_records_a_transaction_within_bounds_and_recomputes_the_position_quantity(): void
    {
        $user = User::factory()->create();
        $position = Position::factory()->for($user)->create([
            'quantity' => 0,
            'average_buy_price' => 0,
        ]);

        $this->recordTransactionWith($user, $position, ['txQuantity' => '2', 'txUnitPrice' => '150.5'])
            ->assertHasNoErrors();

        $transaction = InvestmentTransaction::query()->whereBelongsTo($position)->sole();
        $this->assertEquals(2.0, (float) $transaction->quantity);
        $this->assertEquals(150.5, (float) $transaction->unit_price);
        $this->assertEquals(2.0, (float) $position->fresh()->quantity);
    }

    public static function transactionFields(): array
    {
        return [
            'quantity' => ['txQuantity'],
            'unit price' => ['txUnitPrice'],
        ];
    }

    #[Test]
    #[DataProvider('transactionFields')]
    public function it_accepts_a_transaction_value_at_the_column_scale(string $field): void
    {
        $user = User::factory()->create();
        $position = Position::factory()->for($user)->create();

        $this->recordTransactionWith($user, $position, [$field => '0.00000001'])
            ->assertHasNoErrors();

        $this->assertSame(1, InvestmentTransaction::query()->count());
    }

    #[Test]
    #[DataProvider('transactionFields')]
    public function it_rejects_a_transaction_value_finer_than_the_column_scale(string $field): void
    {
        $user = User::factory()->create();
        $position = Position::factory()->for($user)->create();

        $this->recordTransactionWith($user, $position, [$field => '0.000000001'])
            ->assertHasErrors([$field => 'decimal']);

        $this->assertSame(0, InvestmentTransaction::query()->count());
    }
}
