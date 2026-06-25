<?php

namespace Tests\Feature\Exploration;

use Functional\Exploration\Models\ExploredCell;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserDeletionCascadeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_the_users_explored_cells_when_the_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $cell = ExploredCell::factory()->create(['user_id' => $user->id]);

        $other = User::factory()->create();
        $otherCell = ExploredCell::factory()->create(['user_id' => $other->id]);

        $user->delete();

        $this->assertDatabaseMissing('explored_cells', ['id' => $cell->id]);
        $this->assertDatabaseHas('explored_cells', ['id' => $otherCell->id]);
    }
}
