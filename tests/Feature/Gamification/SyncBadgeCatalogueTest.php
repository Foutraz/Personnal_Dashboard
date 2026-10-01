<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\SyncBadgeCatalogue;
use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Exceptions\InvalidBadgeThresholdException;
use Functional\Gamification\Exceptions\InvalidBadgeTierXpException;
use Functional\Gamification\Exceptions\MissingBadgeThresholdException;
use Functional\Gamification\Exceptions\NonIncreasingBadgeThresholdsException;
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

        $badge = Badge::query()->where('key', BadgeRuleKey::SportDistance->badgeKey(BadgeTier::Gold))->sole();

        $this->assertSame(BadgeRuleKey::SportDistance->value, $badge->rule_key);
        $this->assertSame(BadgeTier::Gold, $badge->tier);
        $this->assertSame('5000.00', $badge->threshold);
        $this->assertSame(BadgeTier::Gold->xpReward(), $badge->xp_reward);
    }

    #[Test]
    public function it_takes_the_domain_of_each_badge_from_its_rule(): void
    {
        $this->app->make(SyncBadgeCatalogue::class)->handle();

        collect($this->app->tagged(BadgeRule::TAG))->each(fn (BadgeRule $rule) => $this->assertSame(
            [$rule->domain()],
            Badge::query()->where('rule_key', $rule->key()->value)->get()->pluck('domain')->unique()->values()->all(),
            $rule->key()->value,
        ));

        $this->assertSame(GamificationDomain::Moto, Badge::query()->where('key', BadgeRuleKey::MotoDistance->badgeKey(BadgeTier::Bronze))->sole()->domain);
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
            BadgeRuleKey::SportDistance->thresholdConfigPath(BadgeTier::Gold) => $newThreshold,
            'gamification.badges.tier_xp.gold' => $newXpReward,
        ]);
        $action->handle();

        $badge = Badge::query()->where('key', BadgeRuleKey::SportDistance->badgeKey(BadgeTier::Gold))->sole();

        $this->assertSame(number_format($newThreshold, 2, '.', ''), $badge->threshold);
        $this->assertSame($newXpReward, $badge->xp_reward);
        $this->assertSame(30, Badge::query()->count());
        $this->assertSame($idsByKey[BadgeRuleKey::SportDistance->badgeKey(BadgeTier::Gold)], $badge->id);
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
        config([BadgeRuleKey::SportDistance->thresholdsConfigPath() => ['bronze' => 100, 'gold' => 5000]]);

        $this->expectExceptionObject(new MissingBadgeThresholdException(BadgeRuleKey::SportDistance, BadgeTier::Silver));

        $this->app->make(SyncBadgeCatalogue::class)->handle();
    }

    #[Test]
    public function it_throws_a_named_exception_when_a_rule_has_no_thresholds_at_all(): void
    {
        config(['gamification.badges.thresholds' => collect(config('gamification.badges.thresholds'))->except(BadgeRuleKey::TodoStreak->value)->all()]);

        $this->expectExceptionObject(new MissingBadgeThresholdException(BadgeRuleKey::TodoStreak, BadgeTier::Bronze));

        $this->app->make(SyncBadgeCatalogue::class)->handle();
    }

    #[Test]
    public function it_throws_a_named_exception_when_a_threshold_is_not_numeric(): void
    {
        config([BadgeRuleKey::SportDistance->thresholdsConfigPath() => ['bronze' => 100, 'silver' => 'a lot', 'gold' => 5000]]);

        $this->expectExceptionObject(new InvalidBadgeThresholdException(BadgeRuleKey::SportDistance, BadgeTier::Silver, 'a lot'));

        $this->app->make(SyncBadgeCatalogue::class)->handle();
    }

    #[Test]
    public function it_writes_nothing_when_a_threshold_is_not_numeric(): void
    {
        config([BadgeRuleKey::MotoDistance->thresholdsConfigPath() => ['bronze' => 100, 'silver' => 1000, 'gold' => ['many']]]);

        $this->assertThrows(
            fn () => $this->app->make(SyncBadgeCatalogue::class)->handle(),
            InvalidBadgeThresholdException::class,
        );

        $this->assertSame(0, Badge::query()->count());
    }

    #[Test]
    public function it_throws_a_named_exception_when_a_tier_xp_is_missing(): void
    {
        config(['gamification.badges.tier_xp' => ['bronze' => 50, 'gold' => 500]]);

        $this->expectExceptionObject(new InvalidBadgeTierXpException(BadgeTier::Silver, null));

        $this->app->make(SyncBadgeCatalogue::class)->handle();
    }

    #[Test]
    public function it_throws_a_named_exception_when_a_tier_xp_is_not_positive(): void
    {
        config(['gamification.badges.tier_xp.gold' => 0]);

        $this->expectExceptionObject(new InvalidBadgeTierXpException(BadgeTier::Gold, 0));

        $this->app->make(SyncBadgeCatalogue::class)->handle();
    }

    #[Test]
    public function it_throws_a_named_exception_when_a_tier_xp_is_not_an_integer(): void
    {
        config(['gamification.badges.tier_xp.bronze' => 'a few']);

        $this->expectExceptionObject(new InvalidBadgeTierXpException(BadgeTier::Bronze, 'a few'));

        $this->app->make(SyncBadgeCatalogue::class)->handle();
    }

    #[Test]
    public function it_throws_a_named_exception_when_the_thresholds_do_not_strictly_increase(): void
    {
        config([BadgeRuleKey::SportDistance->thresholdsConfigPath() => ['bronze' => 1000, 'silver' => 100, 'gold' => 5000]]);

        $this->expectExceptionObject(new NonIncreasingBadgeThresholdsException(BadgeRuleKey::SportDistance, ['bronze' => 1000, 'silver' => 100, 'gold' => 5000]));

        $this->app->make(SyncBadgeCatalogue::class)->handle();
    }

    #[Test]
    public function it_rejects_two_tiers_sharing_the_same_threshold(): void
    {
        config([BadgeRuleKey::TodoStreak->thresholdsConfigPath() => ['bronze' => 7, 'silver' => 30, 'gold' => 30]]);

        $this->expectException(NonIncreasingBadgeThresholdsException::class);

        $this->app->make(SyncBadgeCatalogue::class)->handle();
    }

    #[Test]
    public function it_writes_nothing_when_the_thresholds_do_not_increase(): void
    {
        config([BadgeRuleKey::ExplorationCells->thresholdsConfigPath() => ['bronze' => 5000, 'silver' => 1000, 'gold' => 100]]);

        $this->assertThrows(
            fn () => $this->app->make(SyncBadgeCatalogue::class)->handle(),
            NonIncreasingBadgeThresholdsException::class,
        );

        $this->assertSame(0, Badge::query()->count());
    }

    #[Test]
    public function it_writes_nothing_when_a_tier_xp_is_invalid(): void
    {
        config(['gamification.badges.tier_xp.silver' => -150]);

        $this->assertThrows(
            fn () => $this->app->make(SyncBadgeCatalogue::class)->handle(),
            InvalidBadgeTierXpException::class,
        );

        $this->assertSame(0, Badge::query()->count());
    }

    #[Test]
    public function it_writes_nothing_when_a_threshold_is_missing(): void
    {
        config([BadgeRuleKey::ExplorationCells->thresholdsConfigPath() => ['bronze' => 100]]);

        $this->assertThrows(
            fn () => $this->app->make(SyncBadgeCatalogue::class)->handle(),
            MissingBadgeThresholdException::class,
        );

        $this->assertSame(0, Badge::query()->count());
    }
}
