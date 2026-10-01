<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\ReconvergeBonusXp;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Enums\XpSourceType;
use Functional\Gamification\Models\XpEntry;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReconvergeBonusXpTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stamps_the_user_and_the_rule_key_on_every_entry(): void
    {
        $user = User::factory()->create();

        $this->reconverge($user, XpRuleKey::BadgeAward, [$this->entry('badge-one', 50)]);

        $entry = XpEntry::query()->whereBelongsTo($user)->sole();
        $this->assertSame(XpRuleKey::BadgeAward->value, $entry->rule_key);
        $this->assertSame('badge-one', $entry->source_id);
        $this->assertSame(50, $entry->points);
    }

    #[Test]
    public function it_deletes_the_entries_of_the_key_left_without_a_row(): void
    {
        $user = User::factory()->create();
        $this->ledgerEntry($user, XpRuleKey::BadgeAward, 'kept');
        $orphan = $this->ledgerEntry($user, XpRuleKey::BadgeAward, 'orphan');

        $this->reconverge($user, XpRuleKey::BadgeAward, [$this->entry('kept', 50)]);

        $this->assertFalse(XpEntry::query()->whereKey($orphan->id)->exists());
        $this->assertSame(['kept'], XpEntry::query()->whereBelongsTo($user)->pluck('source_id')->all());
    }

    #[Test]
    public function it_deletes_every_entry_of_the_key_when_no_row_is_given(): void
    {
        $user = User::factory()->create();
        $this->ledgerEntry($user, XpRuleKey::BadgeAward, 'orphan');

        $this->reconverge($user, XpRuleKey::BadgeAward, []);

        $this->assertSame(0, XpEntry::query()->whereBelongsTo($user)->count());
    }

    #[Test]
    public function it_leaves_the_other_keys_and_the_other_users_untouched(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherKey = $this->ledgerEntry($user, XpRuleKey::StreakMilestone, 'sport:7');
        $otherUserEntry = $this->ledgerEntry($otherUser, XpRuleKey::BadgeAward, 'foreign');

        $this->reconverge($user, XpRuleKey::BadgeAward, [$this->entry('badge-one', 50)]);

        $this->assertTrue(XpEntry::query()->whereKey($otherKey->id)->exists());
        $this->assertTrue(XpEntry::query()->whereKey($otherUserEntry->id)->exists());
    }

    #[Test]
    public function it_refreshes_only_the_given_columns_of_an_existing_entry(): void
    {
        $user = User::factory()->create();
        $existing = $this->ledgerEntry($user, XpRuleKey::BadgeAward, 'badge-one');

        $this->reconverge($user, XpRuleKey::BadgeAward, [$this->entry('badge-one', 80)], ['points']);

        $refreshed = $existing->fresh();
        $this->assertSame(80, $refreshed->points);
        $this->assertSame(GamificationDomain::Moto, $refreshed->domain);
        $this->assertSame('2026-09-01 00:00:00', $refreshed->occurred_at->toDateTimeString());
    }

    #[Test]
    public function it_upserts_more_entries_than_one_chunk(): void
    {
        $user = User::factory()->create();
        $entries = array_map(fn (int $position): array => $this->entry("badge-{$position}", 50), range(1, 501));

        $this->reconverge($user, XpRuleKey::BadgeAward, $entries);
        $this->reconverge($user, XpRuleKey::BadgeAward, $entries);

        $this->assertSame(501, XpEntry::query()->whereBelongsTo($user)->count());
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  list<string>  $refreshedColumns
     */
    private function reconverge(User $user, XpRuleKey $ruleKey, array $entries, array $refreshedColumns = ['domain', 'points', 'occurred_at']): void
    {
        $this->app->make(ReconvergeBonusXp::class)->handle($user, $ruleKey, $entries, $refreshedColumns);
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(string $sourceId, int $points): array
    {
        return [
            'domain' => GamificationDomain::Sport->value,
            'source_type' => XpSourceType::Badge->value,
            'source_id' => $sourceId,
            'points' => $points,
            'occurred_at' => Carbon::parse('2026-10-01 10:00:00', 'UTC'),
        ];
    }

    private function ledgerEntry(User $user, XpRuleKey $ruleKey, string $sourceId): XpEntry
    {
        return XpEntry::factory()->create([
            'user_id' => $user->id,
            'domain' => GamificationDomain::Moto,
            'rule_key' => $ruleKey->value,
            'source_type' => XpSourceType::Badge->value,
            'source_id' => $sourceId,
            'points' => 10,
            'occurred_at' => Carbon::parse('2026-09-01 00:00:00', 'UTC'),
        ]);
    }
}
