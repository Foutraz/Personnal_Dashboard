<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerProfilePageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_player_profile_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        PlayerProfile::factory()->create(['user_id' => $user->id, 'total_xp' => 3000, 'level' => 7]);
        XpEntry::factory()->create(['user_id' => $user->id, 'domain' => GamificationDomain::Sport, 'points' => 40, 'occurred_at' => now()->subDay()]);

        $response = $this->actingAs($user, 'web')->get(route('player'));

        $response->assertOk();
        $response->assertSee('Niveau');
        $response->assertSee('3 000');
    }

    #[Test]
    public function it_redirects_guests_to_the_login_page(): void
    {
        $this->get(route('player'))->assertRedirect();
    }

    #[Test]
    public function it_shows_the_user_streaks(): void
    {
        $user = User::factory()->create();
        Streak::factory()->create([
            'user_id' => $user->id,
            'domain' => GamificationDomain::Sport,
            'current_count' => 12,
            'best_count' => 20,
            'last_activity_date' => now()->toDateString(),
        ]);

        $this->actingAs($user, 'web')
            ->get(route('player'))
            ->assertOk()
            ->assertSee('Séries')
            ->assertSee('12')
            ->assertSee('Record : 20');
    }

    #[Test]
    public function it_hides_the_streak_section_when_the_user_has_none(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->get(route('player'))
            ->assertOk()
            ->assertDontSee('Record :');
    }
}
