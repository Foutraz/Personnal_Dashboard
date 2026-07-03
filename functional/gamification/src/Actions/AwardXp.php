<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Services\Dto\LevelTransition;
use Functional\Gamification\Services\Dto\XpAward;
use Functional\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AwardXp
{
    public function __construct(private RefreshPlayerProfile $refreshPlayerProfile) {}

    /**
     * Idempotently upsert the awards into the ledger in batches and refresh the profile.
     *
     * @param  Collection<int, XpAward>  $awards
     */
    public function handle(User $user, Collection $awards): LevelTransition
    {
        $now = now();

        $awards->chunk(500)->each(function (Collection $chunk) use ($user, $now): void {
            DB::table('xp_entries')->upsert(
                $chunk->map(fn (XpAward $award): array => [
                    'id' => strtolower((string) Str::ulid()),
                    'user_id' => $user->id,
                    'domain' => $award->domain->value,
                    'rule_key' => $award->ruleKey,
                    'source_type' => $award->sourceType,
                    'source_id' => $award->sourceId,
                    'points' => $award->points,
                    'occurred_at' => $award->occurredAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
                ['user_id', 'rule_key', 'source_type', 'source_id'],
                ['points', 'occurred_at', 'updated_at'],
            );
        });

        return $this->refreshPlayerProfile->handle($user);
    }
}
