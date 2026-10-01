<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Enums\XpSourceType;
use Functional\Gamification\Models\Badge;
use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Models\XpEntry;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class EvaluateBadges
{
    /**
     * Award the badges of the already synced catalogue whose threshold the tagged rules now reach, reconverge the badge ledger entries and return the new awards.
     *
     * @return Collection<int, BadgeAward>
     */
    public function handle(User $user): Collection
    {
        $newAwards = $this->award($user);
        $this->reconvergeLedger($user);

        return $newAwards;
    }

    /**
     * Insert an award for every unowned badge reached by its rule measure and return those this call actually created.
     *
     * @return Collection<int, BadgeAward>
     */
    private function award(User $user): Collection
    {
        $measures = collect(app()->tagged(BadgeRule::TAG))
            ->mapWithKeys(fn (BadgeRule $rule): array => [$rule->key() => $rule->measure($user)]);

        $ownedBadgeIds = BadgeAward::query()->whereBelongsTo($user)->pluck('badge_id');
        $awardedAt = now();

        $rows = Badge::query()
            ->whereIn('rule_key', $measures->keys())
            ->whereNotIn('id', $ownedBadgeIds)
            ->orderBy('key')
            ->get()
            ->filter(fn (Badge $badge): bool => (float) $badge->threshold <= $measures[$badge->rule_key])
            ->map(fn (Badge $badge): array => [
                'id' => (new BadgeAward)->newUniqueId(),
                'user_id' => $user->id,
                'badge_id' => $badge->id,
                'awarded_at' => $awardedAt,
                'created_at' => $awardedAt,
                'updated_at' => $awardedAt,
            ])
            ->values();

        if ($rows->isEmpty()) {
            return new Collection;
        }

        BadgeAward::query()->insertOrIgnore($rows->all());

        return BadgeAward::query()
            ->with('badge')
            ->whereIn('id', $rows->pluck('id'))
            ->get()
            ->sort(fn (BadgeAward $first, BadgeAward $second): int => [$first->badge->tier->rank(), $first->badge->key] <=> [$second->badge->tier->rank(), $second->badge->key])
            ->values();
    }

    /**
     * Upsert one badge ledger entry per award of the user and delete the entries left without an award.
     */
    private function reconvergeLedger(User $user): void
    {
        $rows = BadgeAward::query()
            ->with('badge')
            ->whereBelongsTo($user)
            ->get()
            ->map(fn (BadgeAward $award): array => [
                'user_id' => $user->id,
                'domain' => $award->badge->domain->value,
                'rule_key' => XpRuleKey::BadgeAward->value,
                'source_type' => XpSourceType::Badge->value,
                'source_id' => $award->badge->key,
                'points' => $award->badge->xp_reward,
                'occurred_at' => $award->awarded_at,
            ])
            ->values();

        XpEntry::query()
            ->whereBelongsTo($user)
            ->where('rule_key', XpRuleKey::BadgeAward->value)
            ->whereNotIn('source_id', $rows->pluck('source_id'))
            ->delete();

        $rows->chunk(500)->each(function (SupportCollection $chunk): void {
            XpEntry::query()->upsert(
                $chunk->all(),
                ['user_id', 'rule_key', 'source_type', 'source_id'],
                ['domain', 'points', 'occurred_at'],
            );
        });
    }
}
