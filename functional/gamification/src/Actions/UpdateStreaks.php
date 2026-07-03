<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Services\Dto\LevelTransition;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateStreaks
{
    public function __construct(private RefreshPlayerProfile $refreshPlayerProfile) {}

    /**
     * Recompute the user's streak projections from the ledger and refresh the profile.
     */
    public function handle(User $user): LevelTransition
    {
        return DB::transaction(function () use ($user): LevelTransition {
            $runsByDomain = $this->activeDaysByDomain($user)->map(fn (Collection $days): Collection => $this->runs($days));

            $this->syncProjections($user, $runsByDomain);
            $this->syncMilestones($user, $runsByDomain);

            return $this->refreshPlayerProfile->handle($user);
        });
    }

    /**
     * Group the distinct non-milestone ledger days by domain, sorted ascending.
     *
     * @return Collection<string, Collection<int, string>>
     */
    private function activeDaysByDomain(User $user): Collection
    {
        return XpEntry::query()
            ->where('user_id', $user->id)
            ->where('rule_key', '!=', Streak::MILESTONE_RULE_KEY)
            ->selectRaw('DISTINCT domain, DATE(occurred_at) as day')
            ->get()
            ->groupBy(fn (XpEntry $entry): string => $entry->domain->value)
            ->map(fn (Collection $entries): Collection => $entries->map(fn (XpEntry $entry): string => (string) $entry->getAttribute('day'))->sort()->values());
    }

    /**
     * Split sorted day strings into consecutive runs of start date and length.
     *
     * @param  Collection<int, string>  $days
     * @return Collection<int, array{start: Carbon, length: int}>
     */
    private function runs(Collection $days): Collection
    {
        $runs = collect();
        $start = null;
        $previous = null;
        $length = 0;

        foreach ($days as $day) {
            $date = Carbon::parse($day)->startOfDay();

            if ($previous === null || ! $date->equalTo($previous->copy()->addDay())) {
                if ($start !== null) {
                    $runs->push(['start' => $start, 'length' => $length]);
                }
                $start = $date;
                $length = 0;
            }

            $length++;
            $previous = $date;
        }

        if ($start !== null) {
            $runs->push(['start' => $start, 'length' => $length]);
        }

        return $runs;
    }

    /**
     * Upsert one streak row per active domain and drop the domains without activity.
     *
     * @param  Collection<string, Collection<int, array{start: Carbon, length: int}>>  $runsByDomain
     */
    private function syncProjections(User $user, Collection $runsByDomain): void
    {
        Streak::query()
            ->where('user_id', $user->id)
            ->whereNotIn('domain', $runsByDomain->keys())
            ->delete();

        $now = now();
        $threshold = $now->copy()->subDay()->toDateString();

        $rows = $runsByDomain->map(function (Collection $runs, string $domain) use ($user, $now, $threshold): array {
            $last = $runs->last();
            $lastDay = $last['start']->copy()->addDays($last['length'] - 1);
            $current = $lastDay->toDateString() >= $threshold ? $last['length'] : 0;

            return [
                'id' => strtolower((string) Str::ulid()),
                'user_id' => $user->id,
                'domain' => $domain,
                'current_count' => $current,
                'best_count' => $runs->max('length'),
                'last_activity_date' => $lastDay->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->values();

        if ($rows->isNotEmpty()) {
            DB::table('streaks')->upsert(
                $rows->all(),
                ['user_id', 'domain'],
                ['current_count', 'best_count', 'last_activity_date', 'updated_at'],
            );
        }
    }

    /**
     * Upsert the reached milestone awards and drop the ones no run reaches anymore.
     *
     * @param  Collection<string, Collection<int, array{start: Carbon, length: int}>>  $runsByDomain
     */
    private function syncMilestones(User $user, Collection $runsByDomain): void
    {
        /** @var array<int, int> $milestones */
        $milestones = config('gamification.streaks.milestones');
        $now = now();

        $rows = $runsByDomain->flatMap(fn (Collection $runs, string $domain): Collection => $runs->flatMap(
            fn (array $run): Collection => collect($milestones)
                ->filter(fn (int $points, int $days): bool => $days <= $run['length'])
                ->map(fn (int $points, int $days): array => [
                    'id' => strtolower((string) Str::ulid()),
                    'user_id' => $user->id,
                    'domain' => $domain,
                    'rule_key' => Streak::MILESTONE_RULE_KEY,
                    'source_type' => Streak::class,
                    'source_id' => $domain.':'.$run['start']->toDateString().':'.$days,
                    'points' => $points,
                    'occurred_at' => $run['start']->copy()->addDays($days - 1),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()
        ))->values();

        XpEntry::query()
            ->where('user_id', $user->id)
            ->where('rule_key', Streak::MILESTONE_RULE_KEY)
            ->whereNotIn('source_id', $rows->pluck('source_id'))
            ->delete();

        $rows->chunk(500)->each(function (Collection $chunk): void {
            DB::table('xp_entries')->upsert(
                $chunk->all(),
                ['user_id', 'rule_key', 'source_type', 'source_id'],
                ['points', 'occurred_at', 'updated_at'],
            );
        });
    }
}
