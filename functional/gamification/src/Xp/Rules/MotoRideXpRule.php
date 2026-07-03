<?php

namespace Functional\Gamification\Xp\Rules;

use Functional\Gamification\Contracts\XpRule;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Services\Dto\XpAward;
use Functional\Moto\Models\MotoRide;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MotoRideXpRule implements XpRule
{
    /**
     * Get the unique ledger key identifying the rule.
     */
    public function key(): string
    {
        return 'moto_ride';
    }

    /**
     * Get the domain the rule awards experience for.
     */
    public function domain(): GamificationDomain
    {
        return GamificationDomain::Moto;
    }

    /**
     * Award a capped base-plus-distance entry for each moto ride.
     *
     * @return Collection<int, XpAward>
     */
    public function awards(User $user, ?Carbon $since): Collection
    {
        $config = config('gamification.xp.moto');

        return MotoRide::query()
            ->where('user_id', $user->id)
            ->when($since, fn ($query) => $query->where('started_at', '>=', $since))
            ->get(['id', 'distance', 'started_at'])
            ->map(fn (MotoRide $ride): XpAward => new XpAward(
                domain: $this->domain(),
                ruleKey: $this->key(),
                sourceType: MotoRide::class,
                sourceId: $ride->id,
                points: $config['ride_base'] + min((int) floor((float) $ride->distance / 20) * $config['distance_per_20km'], $config['distance_cap']),
                occurredAt: $ride->started_at,
            ))
            ->values();
    }
}
