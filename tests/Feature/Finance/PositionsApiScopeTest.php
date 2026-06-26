<?php

namespace Tests\Feature\Finance;

use Functional\Finance\Enums\AssetType;
use Functional\Finance\Models\Position;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PositionsApiScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_only_returns_the_authenticated_users_positions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $ownPositions = Position::factory()->count(2)->create(['user_id' => $user->id]);
        Position::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/positions/search', [
            'search' => [],
        ]);

        $response->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame($ownPositions->pluck('id')->sort()->values()->all(), $returnedIds);
    }

    #[Test]
    public function it_assigns_the_authenticated_user_when_creating_a_position(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/positions/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'asset_symbol' => 'VWCE',
                        'asset_name' => 'Vanguard All-World',
                        'asset_type' => AssetType::Etf->value,
                        'quantity' => 5,
                        'average_buy_price' => 100,
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('positions', [
            'asset_symbol' => 'VWCE',
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_forbids_deleting_another_users_position(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $position = Position::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'api')->deleteJson('/api/positions', [
            'resources' => [$position->id],
        ]);

        $this->assertDatabaseHas('positions', [
            'id' => $position->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_forbids_updating_another_users_position(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $position = Position::factory()->create(['user_id' => $other->id, 'asset_name' => 'Original']);

        $response = $this->actingAs($user, 'api')->postJson('/api/positions/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $position->id, 'attributes' => ['asset_name' => 'Hijacked']],
            ],
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('positions', [
            'id' => $position->id,
            'asset_name' => 'Original',
        ]);
    }

    #[Test]
    public function it_allows_the_owner_to_update_and_delete_their_position(): void
    {
        $user = User::factory()->create();

        $position = Position::factory()->create(['user_id' => $user->id, 'asset_name' => 'Original']);

        $this->actingAs($user, 'api')->postJson('/api/positions/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $position->id, 'attributes' => ['asset_name' => 'Updated']],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'asset_name' => 'Updated']);

        $this->actingAs($user, 'api')->deleteJson('/api/positions', [
            'resources' => [$position->id],
        ])->assertOk();

        $this->assertSoftDeleted('positions', ['id' => $position->id]);
    }
}
