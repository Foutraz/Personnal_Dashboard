<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Services\Dto\XpAward;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AwardXp
{
    /**
     * Run outside a transaction, a failure between the purge and the reinsertion loses the window's ledger entries.
     *
     * @param  Collection<int, XpAward>  $awards
     * @param  Collection<int, string>|null  $ruleKeys
     */
    public function handle(User $user, Collection $awards, ?Collection $ruleKeys = null, ?Carbon $windowStart = null): void
    {
        $ruleKeys ??= $awards->map(fn (XpAward $award): string => $award->ruleKey)->unique()->values();

        $this->purgeWindow($user, $ruleKeys, $windowStart);

        $awards->chunk(500)->each(function (Collection $chunk) use ($user): void {
            XpEntry::query()->upsert(
                $chunk->map(fn (XpAward $award): array => [
                    'user_id' => $user->id,
                    'domain' => $award->domain->value,
                    'rule_key' => $award->ruleKey,
                    'source_type' => $award->sourceType,
                    'source_id' => $award->sourceId,
                    'points' => $award->points,
                    'occurred_at' => $award->occurredAt,
                ])->all(),
                ['user_id', 'rule_key', 'source_type', 'source_id'],
                ['points', 'occurred_at'],
            );
        });
    }

    /**
     * @param  Collection<int, string>  $ruleKeys
     */
    private function purgeWindow(User $user, Collection $ruleKeys, ?Carbon $windowStart): void
    {
        if ($ruleKeys->isEmpty()) {
            return;
        }

        XpEntry::query()
            ->whereBelongsTo($user)
            ->where('rule_key', '!=', XpRuleKey::StreakMilestone->value)
            ->when($windowStart, fn ($query) => $query->where('occurred_at', '>=', $windowStart))
            ->delete();
    }
}
