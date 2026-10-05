<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\AwardXp;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Enums\XpSourceType;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Services\Dto\XpAward;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AwardXpTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_writes_awards_to_the_ledger_without_refreshing_the_profile(): void
    {
        $user = User::factory()->create();

        $this->app->make(AwardXp::class)->handle($user, collect([
            $this->award('sport_activity', 'activity-1', 40),
            $this->award('sport_activity', 'activity-2', 25),
        ]));

        $this->assertSame(2, XpEntry::query()->whereBelongsTo($user)->count());
        $this->assertSame(65, (int) XpEntry::query()->whereBelongsTo($user)->sum('points'));
        $this->assertSame(0, PlayerProfile::query()->whereBelongsTo($user)->count());
    }

    #[Test]
    public function it_silently_skips_awards_already_present_in_the_ledger(): void
    {
        $user = User::factory()->create();
        $action = $this->app->make(AwardXp::class);

        $action->handle($user, collect([$this->award('sport_activity', 'activity-1', 40)]));
        $action->handle($user, collect([
            $this->award('sport_activity', 'activity-1', 40),
            $this->award('sport_activity', 'activity-3', 12),
        ]));

        $this->assertSame(2, XpEntry::query()->whereBelongsTo($user)->count());
        $this->assertSame(52, (int) XpEntry::query()->whereBelongsTo($user)->sum('points'));
    }

    #[Test]
    public function it_keeps_streak_milestone_entries_when_purging_the_window(): void
    {
        $user = User::factory()->create();
        $milestone = XpEntry::factory()->create([
            'user_id' => $user->id,
            'rule_key' => XpRuleKey::StreakMilestone->value,
            'occurred_at' => now()->subDay(),
        ]);
        $retired = XpEntry::factory()->create([
            'user_id' => $user->id,
            'rule_key' => 'retired_rule',
            'occurred_at' => now()->subDay(),
        ]);

        $this->app->make(AwardXp::class)->handle(
            $user,
            collect([$this->award('sport_activity', 'activity-1', 40)]),
            collect(['sport_activity']),
            now()->subDays(3)->startOfDay(),
        );

        $this->assertTrue(XpEntry::query()->whereKey($milestone->id)->exists());
        $this->assertFalse(XpEntry::query()->whereKey($retired->id)->exists());
    }

    #[Test]
    public function it_keeps_badge_award_entries_when_purging_the_window(): void
    {
        $user = User::factory()->create();
        $badgeEntry = XpEntry::factory()->create([
            'user_id' => $user->id,
            'rule_key' => XpRuleKey::BadgeAward->value,
            'source_type' => XpSourceType::Badge->value,
            'occurred_at' => now()->subDay(),
        ]);

        $this->app->make(AwardXp::class)->handle(
            $user,
            collect([$this->award('sport_activity', 'activity-1', 40)]),
            collect(['sport_activity']),
            now()->subDays(3)->startOfDay(),
        );

        $this->assertTrue(XpEntry::query()->whereKey($badgeEntry->id)->exists());
    }

    #[Test]
    public function it_keeps_challenge_completed_entries_when_purging_the_window(): void
    {
        $user = User::factory()->create();
        $challengeEntry = XpEntry::factory()->create([
            'user_id' => $user->id,
            'rule_key' => XpRuleKey::ChallengeCompleted->value,
            'source_type' => XpSourceType::Challenge->value,
            'occurred_at' => now()->subDay(),
        ]);

        $this->app->make(AwardXp::class)->handle(
            $user,
            collect([$this->award('sport_activity', 'activity-1', 40)]),
            collect(['sport_activity']),
            now()->subDays(3)->startOfDay(),
        );

        $this->assertTrue(XpEntry::query()->whereKey($challengeEntry->id)->exists());
    }

    #[Test]
    public function it_lists_the_challenge_completed_key_among_the_bonus_rule_keys(): void
    {
        $this->assertTrue(XpRuleKey::ChallengeCompleted->isBonus());
        $this->assertSame('challenge_completed', XpRuleKey::ChallengeCompleted->value);
        $this->assertSame('challenge', XpSourceType::Challenge->value);
        $this->assertSame(['streak_milestone', 'badge_award', 'challenge_completed'], XpRuleKey::bonusKeys());
    }

    /**
     * Build a sport award targeting the given source with the given points.
     */
    private function award(string $ruleKey, string $sourceId, int $points): XpAward
    {
        return new XpAward(
            domain: GamificationDomain::Sport,
            ruleKey: $ruleKey,
            sourceType: 'test-source',
            sourceId: $sourceId,
            points: $points,
            occurredAt: now()->subDay(),
        );
    }
}
