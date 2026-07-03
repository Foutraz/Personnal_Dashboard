<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GamificationApiScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_only_returns_the_authenticated_users_xp_entries(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $ownEntries = XpEntry::factory()->count(2)->create(['user_id' => $user->id]);
        XpEntry::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/xp-entries/search', [
            'search' => [],
        ]);

        $response->assertOk();
        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame($ownEntries->pluck('id')->sort()->values()->all(), $returnedIds);
    }

    #[Test]
    public function it_only_returns_the_authenticated_users_player_profile(): void
    {
        $user = User::factory()->create();
        PlayerProfile::factory()->create(['user_id' => $user->id, 'level' => 7]);
        PlayerProfile::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/player-profiles/search', [
            'search' => [],
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(7, $response->json('data.0.level'));
    }

    #[Test]
    public function it_rejects_creating_xp_entries_through_the_api(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/xp-entries/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => ['points' => 5000],
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $this->assertSame(0, XpEntry::query()->count());
    }

    #[Test]
    public function it_rejects_updating_own_xp_entries_through_the_api(): void
    {
        $user = User::factory()->create();
        $entry = XpEntry::factory()->create(['user_id' => $user->id, 'points' => 10]);

        $response = $this->actingAs($user, 'api')->postJson('/api/xp-entries/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $entry->id,
                    'attributes' => ['points' => 65000],
                ],
            ],
        ]);

        $response->assertForbidden();
        $this->assertSame(10, $entry->fresh()->points);
    }

    #[Test]
    public function it_rejects_deleting_own_xp_entries_through_the_api(): void
    {
        $user = User::factory()->create();
        $entry = XpEntry::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->deleteJson('/api/xp-entries', [
            'resources' => [$entry->id],
        ]);

        $response->assertForbidden();
        $this->assertTrue(XpEntry::query()->whereKey($entry->id)->exists());
    }

    #[Test]
    public function it_rejects_updating_the_own_player_profile_through_the_api(): void
    {
        $user = User::factory()->create();
        $profile = PlayerProfile::factory()->create(['user_id' => $user->id, 'level' => 2]);

        $response = $this->actingAs($user, 'api')->postJson('/api/player-profiles/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $profile->id,
                    'attributes' => ['level' => 99],
                ],
            ],
        ]);

        $response->assertForbidden();
        $this->assertSame(2, $profile->fresh()->level);
    }

    #[Test]
    public function it_only_returns_the_authenticated_users_streaks(): void
    {
        $user = User::factory()->create();
        Streak::factory()->create(['user_id' => $user->id, 'current_count' => 4]);
        Streak::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/streaks/search', [
            'search' => [],
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(4, $response->json('data.0.current_count'));
    }

    #[Test]
    public function it_rejects_updating_own_streaks_through_the_api(): void
    {
        $user = User::factory()->create();
        $streak = Streak::factory()->create(['user_id' => $user->id, 'current_count' => 2]);

        $response = $this->actingAs($user, 'api')->postJson('/api/streaks/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $streak->id,
                    'attributes' => ['current_count' => 999],
                ],
            ],
        ]);

        $response->assertForbidden();
        $this->assertSame(2, $streak->fresh()->current_count);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->postJson('/api/xp-entries/search', ['search' => []])->assertUnauthorized();
        $this->postJson('/api/player-profiles/search', ['search' => []])->assertUnauthorized();
        $this->postJson('/api/streaks/search', ['search' => []])->assertUnauthorized();
    }
}
