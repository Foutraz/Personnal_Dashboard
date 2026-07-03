<?php

namespace Functional\Gamification\Xp\Rules;

use Functional\Gamification\Contracts\XpRule;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Services\Dto\XpAward;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SportActivityXpRule implements XpRule
{
    /**
     * Get the unique ledger key identifying the rule.
     */
    public function key(): string
    {
        return 'sport_activity';
    }

    /**
     * Get the domain the rule awards experience for.
     */
    public function domain(): GamificationDomain
    {
        return GamificationDomain::Sport;
    }

    /**
     * Award a capped base-plus-bonus entry for each synced sport activity.
     *
     * @return Collection<int, XpAward>
     */
    public function awards(User $user, ?Carbon $since): Collection
    {
        $config = config('gamification.xp.sport');

        return SportActivity::query()
            ->where('user_id', $user->id)
            ->when($since, fn ($query) => $query->where('started_at', '>=', $since))
            ->get(['id', 'distance', 'total_elevation_gain', 'started_at'])
            ->map(fn (SportActivity $activity): XpAward => new XpAward(
                domain: $this->domain(),
                ruleKey: $this->key(),
                sourceType: SportActivity::class,
                sourceId: $activity->id,
                points: $this->points($activity, $config),
                occurredAt: $activity->started_at,
            ))
            ->values();
    }

    /**
     * Compute the capped points earned by a single activity.
     *
     * @param  array<string, int>  $config
     */
    private function points(SportActivity $activity, array $config): int
    {
        $distanceBonus = min((int) floor($activity->distance / 1000) * $config['distance_per_km'], $config['distance_cap']);
        $elevationBonus = min((int) floor($activity->total_elevation_gain / 100) * $config['elevation_per_100m'], $config['elevation_cap']);

        return $config['activity_base'] + $distanceBonus + $elevationBonus;
    }
}
