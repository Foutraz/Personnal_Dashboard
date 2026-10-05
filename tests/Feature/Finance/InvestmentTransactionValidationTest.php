<?php

namespace Tests\Feature\Finance;

use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvestmentTransactionValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
        $this->owner = User::factory()->create();
        $this->position = Position::factory()->for($this->owner)->create();
    }

    private function createTransaction(array $attributes): TestResponse
    {
        return $this->actingAs($this->owner, 'api')->postJson('/api/investment-transactions/mutate', [
            'mutate' => [[
                'operation' => 'create',
                'attributes' => [
                    'position_id' => $this->position->id,
                    'type' => TransactionType::Buy->value,
                    'quantity' => 2,
                    'unit_price' => 150.5,
                    'executed_at' => '2026-09-14 08:00:00',
                    ...$attributes,
                ],
            ]],
        ]);
    }

    private function updateTransaction(InvestmentTransaction $transaction, array $attributes): TestResponse
    {
        return $this->actingAs($this->owner, 'api')->postJson('/api/investment-transactions/mutate', [
            'mutate' => [[
                'operation' => 'update',
                'key' => $transaction->id,
                'attributes' => $attributes,
            ]],
        ]);
    }

    private function ownTransaction(): InvestmentTransaction
    {
        return InvestmentTransaction::factory()->for($this->position)->for($this->owner)->create([
            'executed_at' => Carbon::parse('2026-09-20 08:00:00', 'UTC'),
        ]);
    }

    #[Test]
    public function it_rejects_a_transaction_referencing_a_missing_position(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/investment-transactions/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'position_id' => 'missing-position-id',
                        'type' => 'buy',
                        'quantity' => 1,
                        'unit_price' => 10,
                        'executed_at' => '2026-06-01',
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mutate.0.attributes.position_id']);
    }

    #[Test]
    public function it_rejects_a_transaction_referencing_another_users_position(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreignPosition = Position::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/investment-transactions/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'position_id' => $foreignPosition->id,
                        'type' => 'buy',
                        'quantity' => 1,
                        'unit_price' => 10,
                        'executed_at' => '2026-06-01',
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mutate.0.attributes.position_id']);
    }

    #[Test]
    public function it_rejects_a_transaction_with_an_invalid_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/investment-transactions/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'type' => 'banana',
                        'quantity' => 1,
                        'unit_price' => 10,
                        'executed_at' => '2026-06-01',
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mutate.0.attributes.type']);
    }

    public static function acceptedQuantities(): array
    {
        return [
            'maximum' => [1000000000],
            'smallest positive' => ['0.00000001'],
        ];
    }

    public static function rejectedQuantities(): array
    {
        return [
            'zero' => [0],
            'above the maximum' => [1000000001],
            'finer than the column scale' => ['0.000000001'],
        ];
    }

    public static function acceptedUnitPrices(): array
    {
        return [
            'maximum' => [10000000],
            'cents' => [0.01],
            'smallest positive' => ['0.00000001'],
        ];
    }

    public static function rejectedUnitPrices(): array
    {
        return [
            'zero' => [0],
            'above the maximum' => [10000000.01],
            'finer than the column scale' => ['0.000000001'],
        ];
    }

    #[Test]
    #[DataProvider('acceptedQuantities')]
    public function it_accepts_a_buy_quantity_within_bounds(float|int|string $quantity): void
    {
        $this->createTransaction(['type' => TransactionType::Buy->value, 'quantity' => $quantity])->assertOk();

        $this->assertSame(1, InvestmentTransaction::query()->count());
    }

    #[Test]
    #[DataProvider('rejectedQuantities')]
    public function it_rejects_a_buy_quantity_out_of_bounds(float|int|string $quantity): void
    {
        $response = $this->createTransaction(['type' => TransactionType::Buy->value, 'quantity' => $quantity]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.quantity']);
        $this->assertSame(0, InvestmentTransaction::query()->count());
    }

    #[Test]
    #[DataProvider('acceptedUnitPrices')]
    public function it_accepts_a_sell_unit_price_within_bounds(float|int|string $unitPrice): void
    {
        $this->createTransaction(['type' => TransactionType::Sell->value, 'unit_price' => $unitPrice])->assertOk();

        $this->assertSame(1, InvestmentTransaction::query()->count());
    }

    #[Test]
    #[DataProvider('rejectedUnitPrices')]
    public function it_rejects_a_sell_unit_price_out_of_bounds(float|int|string $unitPrice): void
    {
        $response = $this->createTransaction(['type' => TransactionType::Sell->value, 'unit_price' => $unitPrice]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.unit_price']);
        $this->assertSame(0, InvestmentTransaction::query()->count());
    }

    #[Test]
    public function it_accepts_a_plausible_large_purchase(): void
    {
        $this->createTransaction(['quantity' => 1, 'unit_price' => 50000])->assertOk();

        $this->assertSame(1, InvestmentTransaction::query()->count());
    }

    #[Test]
    public function it_rejects_moving_an_own_transaction_to_a_zero_quantity(): void
    {
        $transaction = $this->ownTransaction();
        $storedBefore = InvestmentTransaction::query()->sole()->getAttributes();

        $response = $this->updateTransaction($transaction, ['quantity' => 0]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.quantity']);
        $this->assertSame($storedBefore, InvestmentTransaction::query()->sole()->getAttributes());
    }

    #[Test]
    public function it_accepts_a_transaction_executed_at_the_current_instant(): void
    {
        $this->createTransaction(['executed_at' => '2026-10-01 10:00:00'])->assertOk();

        $this->assertSame(1, InvestmentTransaction::query()->count());
    }

    #[Test]
    public function it_rejects_a_transaction_executed_one_second_in_the_future(): void
    {
        $response = $this->createTransaction(['executed_at' => '2026-10-01 10:00:01']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.executed_at']);
        $this->assertSame(0, InvestmentTransaction::query()->count());
    }

    #[Test]
    public function it_rejects_a_transaction_executed_at_the_epoch(): void
    {
        $response = $this->createTransaction(['executed_at' => '1970-01-01 00:00:00']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.executed_at']);
        $this->assertSame(0, InvestmentTransaction::query()->count());
    }

    #[Test]
    public function it_rejects_moving_an_own_transaction_into_the_future(): void
    {
        $transaction = $this->ownTransaction();

        $response = $this->updateTransaction($transaction, ['executed_at' => '2026-10-02 08:00:00']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.executed_at']);
        $this->assertTrue($transaction->fresh()->executed_at->equalTo(Carbon::parse('2026-09-20 08:00:00', 'UTC')));
    }

    #[Test]
    public function it_rejects_a_client_chosen_creation_timestamp(): void
    {
        $response = $this->createTransaction(['created_at' => '2026-09-01 08:00:00']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.created_at']);
        $this->assertSame(0, InvestmentTransaction::query()->count());
    }

    #[Test]
    public function it_rejects_a_client_chosen_key(): void
    {
        $response = $this->createTransaction(['id' => (new InvestmentTransaction)->newUniqueId()]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.id']);
        $this->assertSame(0, InvestmentTransaction::query()->count());
    }

    #[Test]
    public function it_rejects_a_client_chosen_update_timestamp(): void
    {
        $transaction = $this->ownTransaction();
        $originalUpdatedAt = $transaction->updated_at;

        $response = $this->updateTransaction($transaction, ['updated_at' => '2026-09-25 08:00:00']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.updated_at']);
        $this->assertTrue($transaction->fresh()->updated_at->equalTo($originalUpdatedAt));
    }

    public static function emptyServerManagedFields(): array
    {
        $cases = [];

        foreach (['id', 'created_at', 'updated_at'] as $field) {
            $cases["{$field} null"] = [$field, null];
            $cases["{$field} empty string"] = [$field, ''];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('emptyServerManagedFields')]
    public function it_rejects_an_empty_server_managed_field_on_creation(string $field, ?string $emptyValue): void
    {
        $response = $this->createTransaction([$field => $emptyValue]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(["mutate.0.attributes.{$field}"]);
        $this->assertSame(0, InvestmentTransaction::query()->count());
    }

    #[Test]
    #[DataProvider('emptyServerManagedFields')]
    public function it_rejects_an_empty_server_managed_field_on_update(string $field, ?string $emptyValue): void
    {
        $transaction = $this->ownTransaction();
        $storedBefore = InvestmentTransaction::query()->sole()->getAttributes();

        $response = $this->updateTransaction($transaction, [$field => $emptyValue]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(["mutate.0.attributes.{$field}"]);
        $this->assertSame($storedBefore, InvestmentTransaction::query()->sole()->getAttributes());
    }
}
