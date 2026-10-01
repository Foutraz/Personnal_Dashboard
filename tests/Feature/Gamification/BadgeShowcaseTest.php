<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\SyncBadgeCatalogue;
use Functional\Gamification\Badges\Rules\SportDistanceBadgeRule;
use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Enums\BadgeUnit;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\Badge;
use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Services\BadgeShowcase;
use Functional\Gamification\Services\Dto\BadgeFamilyProgress;
use Functional\Gamification\Services\Dto\BadgeMedal;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BadgeShowcaseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_measures_progress_towards_the_next_tier_from_the_previous_tier(): void
    {
        $user = $this->userWithDistance(150);
        $this->award($user, 'sport_distance_bronze');

        $family = $this->family($user, 'sport_distance');

        $this->assertSame(150.0, $family->currentValue);
        $this->assertSame(1000.0, $family->nextThreshold);
        $this->assertEqualsWithDelta(5.56, $family->percentage, 0.01);
        $this->assertFalse($family->isComplete());
    }

    #[Test]
    public function it_measures_progress_towards_the_first_tier_from_zero(): void
    {
        $user = $this->userWithDistance(50);

        $family = $this->family($user, 'sport_distance');

        $this->assertSame(100.0, $family->nextThreshold);
        $this->assertEqualsWithDelta(50.0, $family->percentage, 0.001);
    }

    #[Test]
    public function it_reports_full_progress_for_a_completed_family(): void
    {
        $user = $this->userWithDistance(6000);
        foreach (['bronze', 'silver', 'gold'] as $tier) {
            $this->award($user, "sport_distance_{$tier}");
        }

        $family = $this->family($user, 'sport_distance');

        $this->assertTrue($family->isComplete());
        $this->assertNull($family->nextThreshold);
        $this->assertSame(100.0, $family->percentage);
        $this->assertSame(3, $family->earnedCount());
    }

    #[Test]
    public function it_clamps_progress_to_zero_when_the_measure_fell_below_the_earned_tier(): void
    {
        $user = $this->userWithDistance(80);
        $this->award($user, 'sport_distance_bronze');

        $family = $this->family($user, 'sport_distance');

        $this->assertSame(1000.0, $family->nextThreshold);
        $this->assertSame(0.0, $family->percentage);
    }

    #[Test]
    public function it_clamps_progress_to_a_hundred_when_the_tier_is_reached_but_not_yet_awarded(): void
    {
        $user = $this->userWithDistance(150);

        $family = $this->family($user, 'sport_distance');

        $this->assertSame(100.0, $family->nextThreshold);
        $this->assertSame(100.0, $family->percentage);
    }

    #[Test]
    public function it_builds_one_family_per_tagged_rule_with_ordered_medals(): void
    {
        $user = User::factory()->create();
        $this->seedCatalogue();
        $this->award($user, 'sport_distance_bronze');

        $families = $this->app->make(BadgeShowcase::class)->families($user);

        $this->assertCount(10, $families);
        $this->assertSame(
            collect($this->app->tagged(BadgeRule::TAG))->map(fn (BadgeRule $rule): string => $rule->key())->all(),
            $families->map(fn (BadgeFamilyProgress $family): string => $family->ruleKey)->all(),
        );
        $families->each(fn (BadgeFamilyProgress $family) => $this->assertSame(
            [BadgeTier::Bronze, BadgeTier::Silver, BadgeTier::Gold],
            array_map(fn (BadgeMedal $medal): BadgeTier => $medal->tier, $family->medals),
        ));
    }

    #[Test]
    public function it_orders_the_medals_by_tier_whatever_the_catalogue_order(): void
    {
        $user = User::factory()->create();
        foreach ([BadgeTier::Gold, BadgeTier::Bronze, BadgeTier::Silver] as $tier) {
            Badge::factory()->create([
                'key' => "sport_distance_{$tier->value}",
                'rule_key' => 'sport_distance',
                'domain' => GamificationDomain::Sport,
                'tier' => $tier,
                'threshold' => $tier->rank() * 100,
            ]);
        }

        $family = $this->app->make(BadgeShowcase::class)->families($user)->sole();

        $this->assertSame(
            [BadgeTier::Bronze, BadgeTier::Silver, BadgeTier::Gold],
            array_map(fn (BadgeMedal $medal): BadgeTier => $medal->tier, $family->medals),
        );
    }

    #[Test]
    public function it_exposes_the_name_domain_and_unit_of_each_family(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();
        $this->seedCatalogue();

        $family = $this->family($user, 'finance_invested_capital');

        $this->assertSame('Capital investi', $family->name);
        $this->assertSame(GamificationDomain::Finance, $family->domain);
        $this->assertSame(BadgeUnit::Euros, $family->unit);
    }

    #[Test]
    public function it_flags_only_the_medals_awarded_to_the_user(): void
    {
        $user = User::factory()->create();
        $this->seedCatalogue();
        $this->award($user, 'sport_distance_silver');
        $this->award(User::factory()->create(), 'sport_distance_bronze');

        $medals = $this->app->make(BadgeShowcase::class)->families($user)->first()->medals;

        $this->assertSame([false, true, false], array_map(fn (BadgeMedal $medal): bool => $medal->earned, $medals));
    }

    #[Test]
    public function it_labels_each_medal_with_its_tier_and_state(): void
    {
        $this->app->setLocale('fr');
        $user = User::factory()->create();
        $this->seedCatalogue();
        $this->award($user, 'sport_distance_bronze');

        $medals = $this->app->make(BadgeShowcase::class)->families($user)->first()->medals;

        $this->assertSame('Bronze : Obtenu', $medals[0]->accessibleLabel());
        $this->assertSame('Argent : Verrouillé', $medals[1]->accessibleLabel());
    }

    #[Test]
    public function it_counts_the_earned_and_total_badges(): void
    {
        $user = User::factory()->create();
        $this->seedCatalogue();
        $this->award($user, 'sport_distance_bronze');
        $this->award(User::factory()->create(), 'sport_distance_silver');

        $showcase = $this->app->make(BadgeShowcase::class);

        $this->assertSame(1, $showcase->earnedCount($user));
        $this->assertSame(30, $showcase->totalCount());
    }

    #[Test]
    public function it_reports_zero_of_zero_with_an_empty_catalogue(): void
    {
        $user = User::factory()->create();

        $showcase = $this->app->make(BadgeShowcase::class);

        $this->assertTrue($showcase->families($user)->isEmpty());
        $this->assertSame(0, $showcase->earnedCount($user));
        $this->assertSame(0, $showcase->totalCount());
        $this->assertSame(0, Badge::query()->count());
    }

    #[Test]
    public function it_ignores_catalogue_badges_of_rules_that_are_no_longer_tagged(): void
    {
        $user = User::factory()->create();
        $this->seedCatalogue();
        $retired = Badge::factory()->create(['key' => 'retired_family_bronze', 'rule_key' => 'retired_family']);
        BadgeAward::factory()->for($user)->for($retired)->create();

        $showcase = $this->app->make(BadgeShowcase::class);

        $this->assertCount(10, $showcase->families($user));
        $this->assertSame(30, $showcase->totalCount());
        $this->assertSame(0, $showcase->earnedCount($user));
    }

    #[Test]
    public function it_measures_each_rule_once_per_call(): void
    {
        $user = User::factory()->create();
        $this->seedCatalogue();
        $spy = $this->spyOnSportDistance();

        $this->app->make(BadgeShowcase::class)->families($user);

        $this->assertSame(1, $spy->measures);
    }

    #[Test]
    public function it_does_not_measure_a_rule_without_catalogue_badges(): void
    {
        $user = User::factory()->create();
        $spy = $this->spyOnSportDistance();

        $this->app->make(BadgeShowcase::class)->families($user);

        $this->assertSame(0, $spy->measures);
    }

    #[Test]
    public function it_loads_the_awards_of_the_user_in_a_single_query(): void
    {
        $user = User::factory()->create();
        $this->seedCatalogue();
        foreach (['bronze', 'silver', 'gold'] as $tier) {
            $this->award($user, "sport_distance_{$tier}");
        }
        $awardsTable = (new BadgeAward)->getTable();

        DB::enableQueryLog();
        $this->app->make(BadgeShowcase::class)->families($user);
        $awardQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], $awardsTable));
        DB::disableQueryLog();

        $this->assertCount(1, $awardQueries);
    }

    private function seedCatalogue(): void
    {
        $this->app->make(SyncBadgeCatalogue::class)->handle();
    }

    private function award(User $user, string $badgeKey): BadgeAward
    {
        return BadgeAward::factory()->for($user)->for(Badge::query()->where('key', $badgeKey)->sole())->create();
    }

    private function userWithDistance(int $kilometres): User
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => $user->id, 'distance' => $kilometres * 1000]);
        $this->seedCatalogue();

        return $user;
    }

    private function family(User $user, string $ruleKey): BadgeFamilyProgress
    {
        return $this->app->make(BadgeShowcase::class)
            ->families($user)
            ->sole(fn (BadgeFamilyProgress $family): bool => $family->ruleKey === $ruleKey);
    }

    private function spyOnSportDistance(): SportDistanceBadgeRule
    {
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

        return $spy;
    }
}
