<?php

namespace Tests\Feature\Finance;

use Functional\Finance\Enums\AssetType;
use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Livewire\PortfolioOverview;
use Functional\Finance\Models\Position;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
}
