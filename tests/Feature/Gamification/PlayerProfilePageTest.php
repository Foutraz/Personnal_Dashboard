<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\PlayerProfile;
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
}
