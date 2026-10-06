<?php

namespace Tests\Feature\Exploration;

use Functional\Exploration\Models\ExploredCell;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExploredCellsApiScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_only_returns_the_authenticated_users_explored_cells(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $ownCells = ExploredCell::factory()->count(2)->create(['user_id' => $user->id]);
        ExploredCell::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/explored-cells/search', [
            'search' => [],
        ]);

        $response->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame($ownCells->pluck('id')->sort()->values()->all(), $returnedIds);
    }

    #[Test]
    public function it_forbids_updating_another_users_explored_cell(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $cell = ExploredCell::factory()->create(['user_id' => $other->id, 'visit_count' => 1]);

        $response = $this->actingAs($user, 'api')->postJson('/api/explored-cells/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $cell->id, 'attributes' => ['visit_count' => 99]],
            ],
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('explored_cells', [
            'id' => $cell->id,
            'visit_count' => 1,
        ]);
    }

    #[Test]
    public function it_forbids_the_owner_to_update_their_explored_cell(): void
    {
        $user = User::factory()->create();

        $cell = ExploredCell::factory()->create(['user_id' => $user->id, 'visit_count' => 1]);

        $this->actingAs($user, 'api')->postJson('/api/explored-cells/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $cell->id, 'attributes' => ['visit_count' => 5]],
            ],
        ])->assertForbidden();

        $this->assertDatabaseHas('explored_cells', ['id' => $cell->id, 'visit_count' => 1]);
    }

    #[Test]
    public function it_forbids_the_owner_to_delete_their_explored_cell(): void
    {
        $user = User::factory()->create();

        $cell = ExploredCell::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'api')->deleteJson('/api/explored-cells', [
            'resources' => [$cell->id],
        ])->assertForbidden();

        $this->assertDatabaseHas('explored_cells', ['id' => $cell->id]);
    }

    #[Test]
    public function it_denies_restoring_and_force_deleting_an_explored_cell_to_its_owner(): void
    {
        $user = User::factory()->create();

        $cell = ExploredCell::factory()->create(['user_id' => $user->id]);

        $this->assertFalse($user->can('restore', $cell));
        $this->assertFalse($user->can('forceDelete', $cell));
    }
}
