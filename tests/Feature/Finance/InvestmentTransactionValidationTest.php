<?php

namespace Tests\Feature\Finance;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvestmentTransactionValidationTest extends TestCase
{
    use RefreshDatabase;

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
}
