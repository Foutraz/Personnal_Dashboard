<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\SyncBadgeCatalogue;
use Functional\Gamification\Badges\Rules\SportDistanceBadgeRule;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Livewire\PlayerProfilePage;
use Functional\Gamification\Models\Badge;
use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Services\Dto\StreakCard;
use Functional\Sport\Models\SportActivity;
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

    #[Test]
    public function it_still_renders_when_the_configured_timezone_is_invalid(): void
    {
        config(['gamification.timezone' => 'Not/A_Zone']);
        $user = User::factory()->create();
        Streak::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'web')->get(route('player'))->assertOk();
    }

    #[Test]
    public function it_shows_the_badge_showcase_with_its_counter_and_translated_family_names(): void
    {
        $this->app->setLocale('fr');
        $user = $this->userWithDistanceAndBronze(150);

        $this->actingAs($user, 'web')
            ->get(route('player'))
            ->assertOk()
            ->assertSee('Badges')
            ->assertSee('1 / 30')
            ->assertSee('Distance sportive')
            ->assertSee('Régularité tâches')
            ->assertDontSee('gamification::');
    }

    #[Test]
    public function it_shows_the_current_value_and_the_next_threshold_with_their_unit(): void
    {
        $this->app->setLocale('fr');
        $user = $this->userWithDistanceAndBronze(150);

        $this->actingAs($user, 'web')
            ->get(route('player'))
            ->assertOk()
            ->assertSee('Actuel : 150 km')
            ->assertSee("Prochain palier : 1\u{202F}000 km");
    }

    #[Test]
    public function it_exposes_the_progress_of_each_family_as_an_accessible_progressbar(): void
    {
        $this->app->setLocale('fr');
        $user = $this->userWithDistanceAndBronze(150);

        $this->actingAs($user, 'web')
            ->get(route('player'))
            ->assertOk()
            ->assertSee('role="progressbar"', false)
            ->assertSee('aria-valuenow="6"', false)
            ->assertSee('aria-valuemin="0"', false)
            ->assertSee('aria-valuemax="100"', false)
            ->assertSee('aria-label="Distance sportive"', false);
    }

    #[Test]
    public function it_gives_every_medal_a_text_alternative_with_its_tier_and_state(): void
    {
        $this->app->setLocale('fr');
        $user = $this->userWithDistanceAndBronze(150);

        $this->actingAs($user, 'web')
            ->get(route('player'))
            ->assertOk()
            ->assertSee('aria-label="Bronze : Obtenu"', false)
            ->assertSee('aria-label="Argent : Verrouillé"', false)
            ->assertSee('aria-label="Or : Verrouillé"', false);
    }

    #[Test]
    public function it_marks_a_completed_family_as_completed(): void
    {
        $this->app->setLocale('fr');
        $user = $this->userWithDistanceAndBronze(6000);
        foreach (['silver', 'gold'] as $tier) {
            BadgeAward::factory()->for($user)->for(Badge::query()->where('key', "sport_distance_{$tier}")->sole())->create();
        }

        $this->actingAs($user, 'web')
            ->get(route('player'))
            ->assertOk()
            ->assertSee('Famille complétée')
            ->assertSee('aria-valuenow="100"', false);
    }

    #[Test]
    public function it_renders_the_badge_showcase_in_english(): void
    {
        $this->app->setLocale('en');
        $user = $this->userWithDistanceAndBronze(150);

        $this->actingAs($user, 'web')
            ->get(route('player'))
            ->assertOk()
            ->assertSee('1 / 30')
            ->assertSee('Sport distance')
            ->assertSee('Current: 150 km')
            ->assertSee('Next tier: 1,000 km')
            ->assertSee('aria-label="Silver: Locked"', false)
            ->assertDontSee('gamification::');
    }

    #[Test]
    public function it_renders_an_empty_catalogue_as_zero_of_zero(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->get(route('player'))
            ->assertOk()
            ->assertSee('Badges')
            ->assertSee('0 / 0')
            ->assertDontSee('role="progressbar"', false);
    }

    #[Test]
    public function it_hands_the_badge_families_and_counters_to_the_view(): void
    {
        $user = $this->userWithDistanceAndBronze(150);

        Livewire::actingAs($user)
            ->test(PlayerProfilePage::class)
            ->assertViewHas('badgeFamilies', fn (Collection $families): bool => $families->count() === 10)
            ->assertViewHas('badgesEarned', 1)
            ->assertViewHas('badgesTotal', 30);
    }

    #[Test]
    public function it_measures_each_badge_rule_once_per_render(): void
    {
        $user = $this->userWithDistanceAndBronze(150);
        $spy = new class extends SportDistanceBadgeRule
        {
            public int $measures = 0;

            public function measure(User $user): float
            {
                $this->measures++;

                return parent::measure($user);
            }
        };
        $this->app->instance(SportDistanceBadgeRule::class, $spy);

        Livewire::actingAs($user)->test(PlayerProfilePage::class);

        $this->assertSame(1, $spy->measures);
    }

    private function userWithDistanceAndBronze(int $kilometres): User
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => $user->id, 'distance' => $kilometres * 1000]);
        $this->app->make(SyncBadgeCatalogue::class)->handle();
        BadgeAward::factory()->for($user)->for(Badge::query()->where('key', 'sport_distance_bronze')->sole())->create();

        return $user;
    }
}
