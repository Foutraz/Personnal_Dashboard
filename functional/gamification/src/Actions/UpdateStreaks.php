<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Enums\XpRuleKey;
use Functional\Gamification\Enums\XpSourceType;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class UpdateStreaks
{
    public function __construct(
        private GamificationCalendar $calendar,
        private ReconvergeBonusXp $reconvergeBonusXp,
    ) {}

    /**
     * Run outside a transaction, a failure between the projection and milestone writes leaves them out of sync.
     */
    public function handle(User $user): void
    {
        $runsByDomain = $this->activeDaysByDomain($user)->map(fn (Collection $days): Collection => $this->runs($days));

        $this->syncProjections($user, $runsByDomain);
        $this->syncMilestones($user, $runsByDomain);
    }

    /**
     * Group the distinct non-bonus ledger days of the streak domains, bucketed in the gamification timezone, by domain and sorted ascending.
     *
     * @return Collection<string, Collection<int, string>>
     */
    private function activeDaysByDomain(User $user): Collection
    {
        return XpEntry::query()
            ->whereBelongsTo($user)
            ->whereNotIn('rule_key', XpRuleKey::bonusKeys())
            ->whereIn('domain', GamificationDomain::streakDomains())
            ->distinct()
            ->get(['domain', 'occurred_at'])
            ->toBase()
            ->groupBy(fn (XpEntry $entry): string => $entry->domain->value)
            ->map(fn (Collection $entries): Collection => $entries
                ->map(fn (XpEntry $entry): string => $this->calendar->dayOf($entry->occurred_at))
                ->unique()
                ->sort()
                ->values());
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
            ->whereBelongsTo($user)
            ->whereNotIn('domain', $runsByDomain->keys())
            ->delete();

        $rows = $runsByDomain->map(function (Collection $runs, string $domain) use ($user): array {
            $last = $runs->last();
            $lastDay = $last['start']->copy()->addDays($last['length'] - 1);
            $current = $this->calendar->isStreakAlive($lastDay) ? $last['length'] : 0;

            return [
                'user_id' => $user->id,
                'domain' => $domain,
                'current_count' => $current,
                'best_count' => $runs->max('length'),
                'last_activity_date' => $lastDay->toDateString(),
            ];
        })->values();

        if ($rows->isNotEmpty()) {
            Streak::query()->upsert(
                $rows->all(),
                ['user_id', 'domain'],
                ['current_count', 'best_count', 'last_activity_date'],
            );
        }
    }

    /**
     * Upsert one award per domain and reached threshold, dated on the first run reaching it, and drop the thresholds no run reaches anymore.
     *
     * @param  Collection<string, Collection<int, array{start: Carbon, length: int}>>  $runsByDomain
     */
    private function syncMilestones(User $user, Collection $runsByDomain): void
    {
        /** @var array<int, int> $milestones */
        $milestones = config('gamification.streaks.milestones');

        $entries = $runsByDomain->flatMap(fn (Collection $runs, string $domain): Collection => collect($milestones)
            ->map(fn (int $points, int $days): ?array => $this->milestoneEntry($domain, $runs, $days, $points))
            ->filter()
            ->values()
        )->values()->all();

        $this->reconvergeBonusXp->handle($user, XpRuleKey::StreakMilestone, $entries, ['points', 'occurred_at']);
    }

    /**
     * @param  Collection<int, array{start: Carbon, length: int}>  $runs
     * @return array<string, mixed>|null
     */
    private function milestoneEntry(string $domain, Collection $runs, int $days, int $points): ?array
    {
        $firstReachingRun = $runs->first(fn (array $run): bool => $run['length'] >= $days);

        if ($firstReachingRun === null) {
            return null;
        }

        return [
            'domain' => $domain,
            'source_type' => XpSourceType::StreakMilestone->value,
            'source_id' => "{$domain}:{$days}",
            'points' => $points,
            'occurred_at' => $firstReachingRun['start']->copy()->addDays($days - 1),
        ];
    }
}
