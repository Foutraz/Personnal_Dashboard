<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\SyncBadgeCatalogue;
use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Exceptions\MissingBadgeThresholdException;
use Functional\Gamification\Models\Badge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncBadgeCatalogueTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_one_badge_per_rule_and_tier(): void
    {
        $this->app->make(SyncBadgeCatalogue::class)->handle();

        $this->assertSame(30, Badge::query()->count());
        $this->assertSame(10, Badge::query()->where('tier', BadgeTier::Gold)->count());
    }

    #[Test]
    public function it_stores_the_key_threshold_and_tier_xp_of_each_badge(): void
    {
        $this->app->make(SyncBadgeCatalogue::class)->handle();

        $badge = Badge::query()->where('key', 'sport_distance_gold')->sole();

        $this->assertSame('sport_distance', $badge->rule_key);
        $this->assertSame(BadgeTier::Gold, $badge->tier);
        $this->assertSame('5000.00', $badge->threshold);
        $this->assertSame(BadgeTier::Gold->xpReward(), $badge->xp_reward);
    }

    #[Test]
    public function it_takes_the_domain_of_each_badge_from_its_rule(): void
    {
        $this->app->make(SyncBadgeCatalogue::class)->handle();

        collect($this->app->tagged('gamification.badge_rules'))->each(fn (BadgeRule $rule) => $this->assertSame(
            [$rule->domain()],
            Badge::query()->where('rule_key', $rule->key())->get()->pluck('domain')->unique()->values()->all(),
            $rule->key(),
        ));

        $this->assertSame(GamificationDomain::Moto, Badge::query()->where('key', 'moto_distance_bronze')->sole()->domain);
    }

    #[Test]
    public function it_keeps_the_existing_ids_on_a_second_call(): void
    {
        $action = $this->app->make(SyncBadgeCatalogue::class);

        $action->handle();
        $idsByKey = Badge::query()->pluck('id', 'key');

        $action->handle();

        $this->assertSame(30, Badge::query()->count());
        $this->assertEquals($idsByKey->all(), Badge::query()->pluck('id', 'key')->all());
    }

    #[Test]
    public function it_propagates_a_changed_threshold_and_tier_xp_from_the_config(): void
    {
        $action = $this->app->make(SyncBadgeCatalogue::class);
        $action->handle();
        $idsByKey = Badge::query()->pluck('id', 'key');
        $newThreshold = faker()->number(6000, 9000);
        $newXpReward = faker()->number(600, 900);

        config([
            'gamification.badges.thresholds.sport_distance.gold' => $newThreshold,
            'gamification.badges.tier_xp.gold' => $newXpReward,
        ]);
        $action->handle();

        $badge = Badge::query()->where('key', 'sport_distance_gold')->sole();

        $this->assertSame(number_format($newThreshold, 2, '.', ''), $badge->threshold);
        $this->assertSame($newXpReward, $badge->xp_reward);
        $this->assertSame(30, Badge::query()->count());
        $this->assertSame($idsByKey['sport_distance_gold'], $badge->id);
    }

    #[Test]
    public function it_leaves_the_badges_of_untagged_rules_in_place(): void
    {
        $legacy = Badge::factory()->create(['rule_key' => 'retired_rule', 'key' => 'retired_rule_gold']);

        $this->app->make(SyncBadgeCatalogue::class)->handle();

        $this->assertSame(31, Badge::query()->count());
        $this->assertTrue(Badge::query()->whereKey($legacy->getKey())->exists());
    }

    #[Test]
    public function it_throws_a_named_exception_when_a_tier_has_no_threshold(): void
    {
        config(['gamification.badges.thresholds.sport_distance' => ['bronze' => 100, 'gold' => 5000]]);

        $this->expectException(MissingBadgeThresholdException::class);
        $this->expectExceptionMessage('sport_distance');
        $this->expectExceptionMessage('silver');

        $this->app->make(SyncBadgeCatalogue::class)->handle();
    }

    #[Test]
    public function it_throws_a_named_exception_when_a_rule_has_no_thresholds_at_all(): void
    {
        config(['gamification.badges.thresholds' => collect(config('gamification.badges.thresholds'))->except('todo_streak')->all()]);

        $this->expectException(MissingBadgeThresholdException::class);
        $this->expectExceptionMessage('todo_streak');

        $this->app->make(SyncBadgeCatalogue::class)->handle();
    }

    #[Test]
    public function it_writes_nothing_when_a_threshold_is_missing(): void
    {
        config(['gamification.badges.thresholds.exploration_cells' => ['bronze' => 100]]);

        $this->assertThrows(
            fn () => $this->app->make(SyncBadgeCatalogue::class)->handle(),
            MissingBadgeThresholdException::class,
        );

        $this->assertSame(0, Badge::query()->count());
    }
}
