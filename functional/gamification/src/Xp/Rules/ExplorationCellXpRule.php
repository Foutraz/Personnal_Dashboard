<?php

namespace Functional\Gamification\Xp\Rules;

use Functional\Exploration\Models\ExploredCell;
use Functional\Gamification\Contracts\XpRule;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Services\Dto\XpAward;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ExplorationCellXpRule implements XpRule
{
    /**
     * Get the unique ledger key identifying the rule.
     */
    public function key(): string
    {
        return 'exploration_daily_cells';
    }

    /**
     * Get the domain the rule awards experience for.
     */
    public function domain(): GamificationDomain
    {
        return GamificationDomain::Exploration;
    }

    /**
     * Award a capped entry per fully elapsed discovery day, re-evaluating every day touched by recently recorded cells.
     *
     * @return Collection<int, XpAward>
     */
    public function awards(User $user, ?Carbon $since): Collection
    {
        $config = config('gamification.xp.exploration');

        $cells = ExploredCell::query()
            ->where('user_id', $user->id)
            ->where('first_seen_at', '<', now()->startOfDay())
            ->get(['id', 'first_seen_at', 'created_at'])
            ->groupBy(fn (ExploredCell $cell): string => $cell->first_seen_at->toDateString());

        return $cells
            ->filter(fn (Collection $dayCells): bool => $since === null || $dayCells->contains(
                fn (ExploredCell $cell): bool => $cell->created_at === null || $cell->created_at->gte($since)
            ))
            ->map(fn (Collection $dayCells, string $day): XpAward => new XpAward(
                domain: $this->domain(),
                ruleKey: $this->key(),
                sourceType: 'period',
                sourceId: $day,
                points: min($dayCells->count() * $config['cell_discovered'], $config['daily_cap']),
                occurredAt: Carbon::parse($day),
            ))
            ->values();
    }
}
