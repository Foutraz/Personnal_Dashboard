<?php

namespace Functional\Gamification\Services;

use Functional\Gamification\Models\XpEntry;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;

class XpLedger
{
    /**
     * Total the user's xp per domain.
     *
     * @return array<string, int>
     */
    public function totalsByDomain(User $user): array
    {
        return XpEntry::query()
            ->where('user_id', $user->id)
            ->selectRaw('domain, SUM(points) as total')
            ->groupBy('domain')
            ->pluck('total', 'domain')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * Sum the xp the user gained since the given date.
     */
    public function gainedSince(User $user, Carbon $since): int
    {
        return (int) XpEntry::query()
            ->where('user_id', $user->id)
            ->where('occurred_at', '>=', $since)
            ->sum('points');
    }

    /**
     * Build a gapless day-indexed xp series covering the last given days.
     *
     * @return array<string, int>
     */
    public function dailySeries(User $user, int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $sums = XpEntry::query()
            ->where('user_id', $user->id)
            ->where('occurred_at', '>=', $start)
            ->selectRaw('DATE(occurred_at) as day, SUM(points) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(0, $days - 1))
            ->mapWithKeys(function (int $offset) use ($start, $sums): array {
                $day = $start->copy()->addDays($offset)->toDateString();

                return [$day => (int) $sums->get($day, 0)];
            })
            ->all();
    }
}
