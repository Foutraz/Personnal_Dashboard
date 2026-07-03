<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Models\Streak;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StreakModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_a_streak_through_its_factory(): void
    {
        $streak = Streak::factory()->create();

        $this->assertTrue(Streak::query()->whereKey($streak->id)->exists());
        $this->assertIsInt($streak->current_count);
        $this->assertIsInt($streak->best_count);
    }

    #[Test]
    public function it_ignores_a_duplicate_streak_for_the_same_domain(): void
    {
        $user = User::factory()->create();
        $streak = Streak::factory()->create(['user_id' => $user->id]);

        DB::table('streaks')->insertOrIgnore([
            'id' => strtolower((string) str()->ulid()),
            'user_id' => $user->id,
            'domain' => $streak->domain->value,
            'current_count' => 1,
            'best_count' => 1,
            'last_activity_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, Streak::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function it_deletes_the_streaks_when_the_user_is_deleted(): void
    {
        $streak = Streak::factory()->create();

        $streak->user->delete();

        $this->assertSame(0, Streak::query()->count());
    }
}
