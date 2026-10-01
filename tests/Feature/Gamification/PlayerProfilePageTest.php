<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Livewire\PlayerProfilePage;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Services\Dto\StreakCard;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerProfilePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-07-10 12:00', 'Europe/Paris')->utc());
    }

    #[Test]
    public function it_renders_the_player_profile_for_the_authenticated_user(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();
        PlayerProfile::factory()->create(['user_id' => $user->id, 'total_xp' => 3000, 'level' => 7]);
        XpEntry::factory()->create(['user_id' => $user->id, 'domain' => GamificationDomain::Sport, 'points' => 40, 'occurred_at' => now()->subDay()]);

        $response = $this->actingAs($user, 'web')->get(route('player'));

        $response->assertOk();
        $response->assertSee('<title>Joueur</title>', false);
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
        $this->app->setLocale('fr');
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
        $this->app->setLocale('fr');
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->get(route('player'))
            ->assertOk()
            ->assertDontSee('Record :');
    }

    #[Test]
    public function it_flags_only_the_live_streaks_as_burning(): void
    {
        $user = User::factory()->create();
        Streak::factory()->create([
            'user_id' => $user->id,
            'domain' => GamificationDomain::Sport,
            'current_count' => 5,
            'last_activity_date' => now()->subDay()->toDateString(),
        ]);
        Streak::factory()->create([
            'user_id' => $user->id,
            'domain' => GamificationDomain::Todo,
            'current_count' => 3,
            'last_activity_date' => now()->subDays(2)->toDateString(),
        ]);

        Livewire::actingAs($user)
            ->test(PlayerProfilePage::class)
            ->assertViewHas('streakCards', fn (Collection $cards): bool => $cards->mapWithKeys(
                fn (StreakCard $card): array => [$card->domain->value => $card->isAlive]
            )->all() === ['sport' => true, 'todo' => false]);
    }

    #[Test]
    public function it_renders_the_streak_section_in_english(): void
    {
        $this->app->setLocale('en');
        $user = User::factory()->create();
        Streak::factory()->create([
            'user_id' => $user->id,
            'domain' => GamificationDomain::Health,
            'current_count' => 4,
            'best_count' => 9,
            'last_activity_date' => now()->toDateString(),
        ]);

        $this->actingAs($user, 'web')
            ->get(route('player'))
            ->assertOk()
            ->assertSee('Streaks')
            ->assertSee('Best: 9')
            ->assertSee('Health')
            ->assertDontSee('gamification::');
    }

    #[Test]
    public function it_shows_a_zero_current_count_on_a_streak_that_is_no_longer_alive(): void
    {
        $user = User::factory()->create();
        Streak::factory()->create([
            'user_id' => $user->id,
            'domain' => GamificationDomain::Sport,
            'current_count' => 6,
            'best_count' => 8,
            'last_activity_date' => now()->subDays(3)->toDateString(),
        ]);

        Livewire::actingAs($user)
            ->test(PlayerProfilePage::class)
            ->assertViewHas('streakCards', fn (Collection $cards): bool => $cards->sole()->currentCount === 0
                && $cards->sole()->bestCount === 8
                && ! $cards->sole()->isAlive);
    }
}
