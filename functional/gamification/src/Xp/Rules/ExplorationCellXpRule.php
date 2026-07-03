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
     * Award a capped entry per fully elapsed day of newly discovered cells.
     *
     * @return Collection<int, XpAward>
     */
    public function awards(User $user, ?Carbon $since): Collection
    {
        $config = config('gamification.xp.exploration');

        return ExploredCell::query()
            ->where('user_id', $user->id)
            ->where('first_seen_at', '<', now()->startOfDay())
            ->when($since, fn ($query) => $query->where('first_seen_at', '>=', $since->copy()->startOfDay()))
            ->get(['id', 'first_seen_at'])
            ->groupBy(fn (ExploredCell $cell): string => $cell->first_seen_at->toDateString())
            ->map(fn (Collection $cells, string $day): XpAward => new XpAward(
                domain: $this->domain(),
                ruleKey: $this->key(),
                sourceType: 'period',
                sourceId: $day,
                points: min($cells->count() * $config['cell_discovered'], $config['daily_cap']),
                occurredAt: Carbon::parse($day),
            ))
            ->values();
    }
}
