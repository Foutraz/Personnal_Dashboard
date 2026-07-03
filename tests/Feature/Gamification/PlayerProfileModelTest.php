<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Models\PlayerProfile;
use Functional\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerProfileModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_a_player_profile_through_its_factory(): void
    {
        $profile = PlayerProfile::factory()->create();

        $this->assertTrue(PlayerProfile::query()->whereKey($profile->id)->exists());
        $this->assertIsInt($profile->total_xp);
        $this->assertIsInt($profile->level);
    }

    #[Test]
    public function it_allows_a_single_profile_per_user(): void
    {
        $user = User::factory()->create();
        PlayerProfile::factory()->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);

        PlayerProfile::factory()->create(['user_id' => $user->id]);
    }
}
