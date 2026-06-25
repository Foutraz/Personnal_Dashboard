<?php

namespace Tests\Feature\Finance;

use Functional\Finance\Enums\AssetType;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PositionValidationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_rejects_a_position_with_an_invalid_asset_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/positions/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'asset_symbol' => 'AAPL',
                        'asset_name' => 'Apple',
                        'asset_type' => 'banana',
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mutate.0.attributes.asset_type']);
    }

    #[Test]
    public function it_rejects_a_position_with_a_negative_quantity(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/positions/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'asset_symbol' => 'AAPL',
                        'asset_name' => 'Apple',
                        'asset_type' => AssetType::Stock->value,
                        'quantity' => -5,
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mutate.0.attributes.quantity']);
    }

    #[Test]
    public function it_accepts_a_valid_position(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/positions/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'asset_symbol' => 'AAPL',
                        'asset_name' => 'Apple',
                        'asset_type' => AssetType::Stock->value,
                        'quantity' => 10,
                        'average_buy_price' => 150.25,
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('positions', ['asset_symbol' => 'AAPL', 'user_id' => $user->id]);
    }
}
