<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Enums\XpSourceType;
use Functional\Gamification\Models\Badge;
use Functional\Gamification\Models\BadgeAward;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;

class EvaluateBadges
{
    public function __construct(private ReconvergeBonusXp $reconvergeBonusXp) {}

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
            ->mapWithKeys(fn (BadgeRule $rule): array => [$rule->key()->value => $rule->measure($user)]);

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
                'measured_value' => round($measures[$badge->rule_key], 2),
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
        $entries = BadgeAward::query()
            ->with('badge')
            ->whereBelongsTo($user)
            ->get()
            ->map(fn (BadgeAward $award): array => [
                'domain' => $award->badge->domain->value,
                'source_type' => XpSourceType::Badge->value,
                'source_id' => $award->badge->key,
                'points' => $award->badge->xp_reward,
                'occurred_at' => $award->awarded_at,
            ])
            ->values()
            ->all();

        $this->reconvergeBonusXp->handle($user, XpRuleKey::BadgeAward, $entries, ['domain', 'points', 'occurred_at']);
    }
}
