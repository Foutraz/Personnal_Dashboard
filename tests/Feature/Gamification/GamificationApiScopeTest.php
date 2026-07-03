<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Models\PlayerProfile;
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
    public function it_requires_authentication(): void
    {
        $this->postJson('/api/xp-entries/search', ['search' => []])->assertUnauthorized();
        $this->postJson('/api/player-profiles/search', ['search' => []])->assertUnauthorized();
    }
}
