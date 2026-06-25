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
}
