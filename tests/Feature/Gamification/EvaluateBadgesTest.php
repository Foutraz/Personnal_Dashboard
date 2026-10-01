<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\EvaluateBadges;
use Functional\Gamification\Actions\SyncBadgeCatalogue;
use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Enums\XpSourceType;
use Functional\Gamification\Models\Badge;
use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EvaluateBadgesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_awards_the_reached_badge_with_its_ledger_xp(): void
    {
        $user = User::factory()->create();
        $this->activities($user, 10);

        $awards = $this->evaluate($user);

        $this->assertSame(['sport_activity_count_bronze'], $awards->map(fn (BadgeAward $award): string => $award->badge->key)->all());
        $entry = $this->badgeEntries($user)->sole();
        $this->assertSame(BadgeTier::Bronze->xpReward(), $entry->points);
        $this->assertSame(GamificationDomain::Sport, $entry->domain);
        $this->assertSame(XpSourceType::Badge->value, $entry->source_type);
        $this->assertSame('sport_activity_count_bronze', $entry->source_id);
        $this->assertTrue($entry->occurred_at->equalTo($awards->sole()->awarded_at));
    }

    #[Test]
    public function it_awards_nothing_below_the_threshold(): void
    {
        $user = User::factory()->create();
        $this->activities($user, 9);

        $awards = $this->evaluate($user);

        $this->assertTrue($awards->isEmpty());
        $this->assertSame(0, BadgeAward::query()->count());
        $this->assertSame(0, XpEntry::query()->count());
    }

    #[Test]
    public function it_awards_nothing_to_a_user_without_data(): void
    {
        $user = User::factory()->create();

        $awards = $this->evaluate($user);

        $this->assertTrue($awards->isEmpty());
        $this->assertSame(0, BadgeAward::query()->count());
        $this->assertSame(0, XpEntry::query()->count());
    }

    #[Test]
    public function it_awards_every_tier_reached_in_the_same_pass(): void
    {
        config(['gamification.badges.thresholds.sport_activity_count' => ['bronze' => 1, 'silver' => 2, 'gold' => 3]]);
        $user = User::factory()->create();
        $this->activities($user, 3);

        $awards = $this->evaluate($user);

        $this->assertCount(3, $awards);
        $this->assertSame(
            collect(BadgeTier::cases())->sum(fn (BadgeTier $tier): int => $tier->xpReward()),
            (int) $this->badgeEntries($user)->sum('points'),
        );
    }

    #[Test]
    public function it_awards_a_streak_badge_from_the_best_streak_projection(): void
    {
        $user = User::factory()->create();
        Streak::factory()->for($user)->create(['domain' => GamificationDomain::Sport, 'current_count' => 0, 'best_count' => 7]);

        $awards = $this->evaluate($user);

        $this->assertSame(['sport_streak_bronze'], $awards->map(fn (BadgeAward $award): string => $award->badge->key)->all());
    }

    #[Test]
    public function it_leaves_the_catalogue_untouched(): void
    {
        $user = User::factory()->create();
        $this->activities($user, 10);

        $awards = $this->app->make(EvaluateBadges::class)->handle($user);

        $this->assertTrue($awards->isEmpty());
        $this->assertSame(0, Badge::query()->count());
    }

    #[Test]
    public function it_neither_duplicates_the_award_nor_the_xp_on_a_second_pass(): void
    {
        $user = User::factory()->create();
        $this->activities($user, 10);
        $first = $this->evaluate($user);
        $entry = $this->badgeEntries($user)->sole();

        $second = $this->evaluate($user);

        $this->assertTrue($second->isEmpty());
        $this->assertSame(1, BadgeAward::query()->whereBelongsTo($user)->count());
        $this->assertTrue($first->sole()->awarded_at->equalTo(BadgeAward::query()->whereBelongsTo($user)->sole()->awarded_at));
        $this->assertSame($entry->id, $this->badgeEntries($user)->sole()->id);
    }

    #[Test]
    public function it_keeps_the_badge_and_its_xp_when_the_measure_drops(): void
    {
        $user = User::factory()->create();
        $this->activities($user, 10);
        $this->evaluate($user);

        SportActivity::query()->whereBelongsTo($user)->delete();
        $awards = $this->evaluate($user);

        $this->assertTrue($awards->isEmpty());
        $this->assertSame(1, BadgeAward::query()->whereBelongsTo($user)->count());
        $this->assertSame(BadgeTier::Bronze->xpReward(), (int) $this->badgeEntries($user)->sum('points'));
    }

    #[Test]
    public function it_restores_a_missing_ledger_entry_without_awarding_again(): void
    {
        $user = User::factory()->create();
        $this->activities($user, 10);
        $this->evaluate($user);
        $this->badgeEntries($user)->each->delete();

        $awards = $this->evaluate($user);

        $this->assertTrue($awards->isEmpty());
        $this->assertSame(BadgeTier::Bronze->xpReward(), $this->badgeEntries($user)->sole()->points);
    }

    #[Test]
    public function it_removes_the_badge_entries_without_a_matching_award(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->activities($user, 10);
        $orphan = XpEntry::factory()->create([
            'user_id' => $user->id,
            'rule_key' => XpRuleKey::BadgeAward->value,
            'source_type' => XpSourceType::Badge->value,
            'source_id' => 'retired_rule_gold',
        ]);
        $foreign = XpEntry::factory()->create([
            'user_id' => $other->id,
            'rule_key' => XpRuleKey::BadgeAward->value,
            'source_type' => XpSourceType::Badge->value,
            'source_id' => 'retired_rule_gold',
        ]);

        $this->evaluate($user);

        $this->assertFalse(XpEntry::query()->whereKey($orphan->id)->exists());
        $this->assertTrue(XpEntry::query()->whereKey($foreign->id)->exists());
        $this->assertSame(['sport_activity_count_bronze'], $this->badgeEntries($user)->pluck('source_id')->all());
    }

    #[Test]
    public function it_follows_a_changed_tier_xp_on_the_next_pass(): void
    {
        $user = User::factory()->create();
        $this->activities($user, 10);
        $this->evaluate($user);
        $newXpReward = faker()->number(60, 90);

        config(['gamification.badges.tier_xp.bronze' => $newXpReward]);
        $this->evaluate($user);

        $this->assertSame($newXpReward, $this->badgeEntries($user)->sole()->points);
    }

    #[Test]
    public function it_leaves_the_badges_of_other_users_untouched(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->activities($other, 10);
        $this->evaluate($other);

        $awards = $this->evaluate($user);

        $this->assertTrue($awards->isEmpty());
        $this->assertSame(1, BadgeAward::query()->whereBelongsTo($other)->count());
        $this->assertSame(1, $this->badgeEntries($other)->count());
    }

    /**
     * @return Collection<int, BadgeAward>
     */
    private function evaluate(User $user): Collection
    {
        $this->app->make(SyncBadgeCatalogue::class)->handle();

        return $this->app->make(EvaluateBadges::class)->handle($user);
    }

    private function activities(User $user, int $count): void
    {
        SportActivity::factory()->count($count)->create(['user_id' => $user->id, 'distance' => 1000.0]);
    }

    /**
     * @return Collection<int, XpEntry>
     */
    private function badgeEntries(User $user): Collection
    {
        return XpEntry::query()->whereBelongsTo($user)->where('rule_key', XpRuleKey::BadgeAward->value)->get();
    }
}
