<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\ReconvergeChallengeXp;
use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Enums\XpSourceType;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Models\XpEntry;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReconvergeChallengeXpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-03 12:00:00', 'UTC'));
    }

    #[Test]
    public function it_records_one_ledger_entry_per_completed_challenge(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->completed()->create([
            'user_id' => $user->id,
            'xp_reward' => 50,
            'resolved_at' => Carbon::parse('2026-10-02 17:30:00', 'UTC'),
        ]);

        $this->reconverge($user);

        $entry = XpEntry::query()->whereBelongsTo($user)->sole();
        $this->assertSame(XpRuleKey::ChallengeCompleted->value, $entry->rule_key);
        $this->assertSame(XpSourceType::Challenge->value, $entry->source_type);
        $this->assertSame($challenge->id, $entry->source_id);
        $this->assertSame(50, $entry->points);
        $this->assertSame(GamificationDomain::Sport, $entry->domain);
        $this->assertSame('2026-10-02 17:30:00', $entry->occurred_at->toDateTimeString());
    }

    #[Test]
    public function it_uses_the_domain_and_the_frozen_reward_of_each_challenge(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->completed()->forTemplate(ChallengeTemplateKey::MotoDistance)->create(['user_id' => $user->id, 'xp_reward' => 80]);

        $this->reconverge($user);

        $entry = XpEntry::query()->whereBelongsTo($user)->sole();
        $this->assertSame(GamificationDomain::Moto, $entry->domain);
        $this->assertSame(80, $entry->points);
    }

    #[Test]
    public function it_ignores_the_challenges_that_are_not_completed(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->create(['user_id' => $user->id]);
        Challenge::factory()->accepted()->forTemplate(ChallengeTemplateKey::MotoDistance)->create(['user_id' => $user->id]);
        Challenge::factory()->failed()->forTemplate(ChallengeTemplateKey::ExplorationCells)->create(['user_id' => $user->id]);
        Challenge::factory()->declined()->forTemplate(ChallengeTemplateKey::SportElevation)->create(['user_id' => $user->id]);
        Challenge::factory()->expired()->forTemplate(ChallengeTemplateKey::SportMovingTime)->create(['user_id' => $user->id]);

        $this->reconverge($user);

        $this->assertSame(0, XpEntry::query()->whereBelongsTo($user)->count());
    }

    #[Test]
    public function it_deletes_an_orphan_challenge_entry(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->completed()->create(['user_id' => $user->id]);
        $orphan = XpEntry::factory()->create([
            'user_id' => $user->id,
            'rule_key' => XpRuleKey::ChallengeCompleted->value,
            'source_type' => XpSourceType::Challenge->value,
            'source_id' => (new Challenge)->newUniqueId(),
        ]);

        $this->reconverge($user);

        $this->assertFalse(XpEntry::query()->whereKey($orphan->id)->exists());
        $this->assertSame($challenge->id, XpEntry::query()->whereBelongsTo($user)->sole()->source_id);
    }

    #[Test]
    public function it_deletes_every_challenge_entry_when_no_challenge_is_completed(): void
    {
        $user = User::factory()->create();
        XpEntry::factory()->create([
            'user_id' => $user->id,
            'rule_key' => XpRuleKey::ChallengeCompleted->value,
            'source_type' => XpSourceType::Challenge->value,
            'source_id' => (new Challenge)->newUniqueId(),
        ]);

        $this->reconverge($user);

        $this->assertSame(0, XpEntry::query()->whereBelongsTo($user)->count());
    }

    #[Test]
    public function it_keeps_a_single_entry_across_passes(): void
    {
        $user = User::factory()->create();
        Challenge::factory()->completed()->create(['user_id' => $user->id]);

        $this->reconverge($user);
        $this->reconverge($user);

        $this->assertSame(1, XpEntry::query()->whereBelongsTo($user)->count());
    }

    #[Test]
    public function it_realigns_a_stale_entry_with_its_challenge(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->completed()->create([
            'user_id' => $user->id,
            'xp_reward' => 50,
            'resolved_at' => Carbon::parse('2026-10-02 17:30:00', 'UTC'),
        ]);
        $stale = XpEntry::factory()->create([
            'user_id' => $user->id,
            'domain' => GamificationDomain::Moto,
            'rule_key' => XpRuleKey::ChallengeCompleted->value,
            'source_type' => XpSourceType::Challenge->value,
            'source_id' => $challenge->id,
            'points' => 10,
            'occurred_at' => Carbon::parse('2026-09-01 00:00:00', 'UTC'),
        ]);

        $this->reconverge($user);

        $entry = XpEntry::query()->whereBelongsTo($user)->sole();
        $this->assertSame($stale->id, $entry->id);
        $this->assertSame(50, $entry->points);
        $this->assertSame(GamificationDomain::Sport, $entry->domain);
        $this->assertSame('2026-10-02 17:30:00', $entry->occurred_at->toDateTimeString());
    }

    #[Test]
    public function it_leaves_the_other_ledger_entries_and_the_other_users_untouched(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $baseEntry = XpEntry::factory()->create(['user_id' => $user->id, 'rule_key' => XpRuleKey::SportActivity->value]);
        $badgeEntry = XpEntry::factory()->create(['user_id' => $user->id, 'rule_key' => XpRuleKey::BadgeAward->value, 'source_type' => XpSourceType::Badge->value]);
        $otherChallenge = Challenge::factory()->completed()->create(['user_id' => $otherUser->id]);
        $otherEntry = XpEntry::factory()->create([
            'user_id' => $otherUser->id,
            'rule_key' => XpRuleKey::ChallengeCompleted->value,
            'source_type' => XpSourceType::Challenge->value,
            'source_id' => $otherChallenge->id,
        ]);

        $this->reconverge($user);

        $this->assertTrue(XpEntry::query()->whereKey($baseEntry->id)->exists());
        $this->assertTrue(XpEntry::query()->whereKey($badgeEntry->id)->exists());
        $this->assertTrue(XpEntry::query()->whereKey($otherEntry->id)->exists());
    }

    private function reconverge(User $user): void
    {
        $this->app->make(ReconvergeChallengeXp::class)->handle($user);
    }
}
