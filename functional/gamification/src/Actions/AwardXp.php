<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Services\Dto\LevelTransition;
use Functional\Gamification\Services\Dto\XpAward;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AwardXp
{
    public function __construct(private RefreshPlayerProfile $refreshPlayerProfile) {}

    /**
     * Replace the recalculated rule keys within the window, reinsert the current awards and refresh the profile.
     *
     * @param  Collection<int, XpAward>  $awards
     * @param  Collection<int, string>|null  $ruleKeys
     */
    public function handle(User $user, Collection $awards, ?Collection $ruleKeys = null, ?Carbon $windowStart = null): LevelTransition
    {
        $ruleKeys ??= $awards->map(fn (XpAward $award): string => $award->ruleKey)->unique()->values();
        $now = now();

        return DB::transaction(function () use ($user, $awards, $ruleKeys, $windowStart, $now): LevelTransition {
            $this->purgeWindow($user, $ruleKeys, $windowStart);

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
        });
    }

    /**
     * Delete the recalculated rule entries falling inside the recomputed window before they are reinserted.
     *
     * @param  Collection<int, string>  $ruleKeys
     */
    private function purgeWindow(User $user, Collection $ruleKeys, ?Carbon $windowStart): void
    {
        if ($ruleKeys->isEmpty()) {
            return;
        }

        XpEntry::query()
            ->where('user_id', $user->id)
            ->whereIn('rule_key', $ruleKeys->all())
            ->when($windowStart, fn ($query) => $query->where('occurred_at', '>=', $windowStart))
            ->delete();
    }
}
