<?php

namespace Tests\Feature\Goals;

use Functional\Goals\Models\Goal;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserDeletionCascadeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_the_users_goals_when_the_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->manual()->create(['user_id' => $user->id]);

        $other = User::factory()->create();
        $otherGoal = Goal::factory()->manual()->create(['user_id' => $other->id]);

        $user->delete();

        $this->assertSoftDeleted('goals', ['id' => $goal->id]);
        $this->assertDatabaseHas('goals', ['id' => $otherGoal->id, 'deleted_at' => null]);
    }
}
