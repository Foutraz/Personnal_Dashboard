<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Exceptions\NonBonusXpRuleKeyException;
use Functional\Gamification\Models\XpEntry;
use Functional\Users\Models\User;
use Illuminate\Support\Collection;

class ReconvergeBonusXp
{
    private const CHUNK_SIZE = 500;

    /**
     * Upsert one ledger entry per row under the bonus rule key and delete the entries of that key left without a row.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @param  list<string>  $refreshedColumns
     *
     * @throws NonBonusXpRuleKeyException
     */
    public function handle(User $user, XpRuleKey $ruleKey, array $entries, array $refreshedColumns): void
    {
        if (! $ruleKey->isBonus()) {
            throw NonBonusXpRuleKeyException::for($ruleKey);
        }

        $entries = collect($entries);

        XpEntry::query()
            ->whereBelongsTo($user)
            ->where('rule_key', $ruleKey->value)
            ->whereNotIn('source_id', $entries->pluck('source_id'))
            ->delete();

        $entries
            ->map(fn (array $entry): array => [...$entry, 'user_id' => $user->id, 'rule_key' => $ruleKey->value])
            ->chunk(self::CHUNK_SIZE)
            ->each(function (Collection $chunk) use ($refreshedColumns): void {
                XpEntry::query()->upsert(
                    $chunk->all(),
                    ['user_id', 'rule_key', 'source_type', 'source_id'],
                    $refreshedColumns,
                );
            });
    }
}
