<?php

namespace Tests\Feature\Finance;

use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvestmentTransactionsApiScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_only_returns_the_authenticated_users_transactions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $ownPosition = Position::factory()->create(['user_id' => $user->id]);
        $otherPosition = Position::factory()->create(['user_id' => $other->id]);

        $ownTransactions = InvestmentTransaction::factory()->count(2)->create([
            'position_id' => $ownPosition->id,
            'user_id' => $user->id,
        ]);

        InvestmentTransaction::factory()->count(3)->create([
            'position_id' => $otherPosition->id,
            'user_id' => $other->id,
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/investment-transactions/search', [
            'search' => [],
        ]);

        $response->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame($ownTransactions->pluck('id')->sort()->values()->all(), $returnedIds);
    }

    #[Test]
    public function it_assigns_the_position_owner_when_creating_a_transaction(): void
    {
        $user = User::factory()->create();
        $position = Position::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/investment-transactions/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'position_id' => $position->id,
                        'type' => TransactionType::Buy->value,
                        'quantity' => 3,
                        'unit_price' => 120,
                        'executed_at' => now()->toDateTimeString(),
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('investment_transactions', [
            'position_id' => $position->id,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_forbids_updating_another_users_transaction(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $position = Position::factory()->create(['user_id' => $other->id]);
        $transaction = InvestmentTransaction::factory()->create([
            'position_id' => $position->id,
            'user_id' => $other->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/investment-transactions/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $transaction->id, 'attributes' => ['quantity' => 99]],
            ],
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('investment_transactions', [
            'id' => $transaction->id,
            'quantity' => 1,
        ]);
    }

    #[Test]
    public function it_allows_the_owner_to_update_and_delete_their_transaction(): void
    {
        $user = User::factory()->create();

        $position = Position::factory()->create(['user_id' => $user->id]);
        $transaction = InvestmentTransaction::factory()->create([
            'position_id' => $position->id,
            'user_id' => $user->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user, 'api')->postJson('/api/investment-transactions/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $transaction->id, 'attributes' => ['quantity' => 7]],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('investment_transactions', ['id' => $transaction->id, 'quantity' => 7]);

        $this->actingAs($user, 'api')->deleteJson('/api/investment-transactions', [
            'resources' => [$transaction->id],
        ])->assertOk();

        $this->assertSoftDeleted('investment_transactions', ['id' => $transaction->id]);
    }
}
