<?php

namespace Functional\Gamification\Xp\Rules;

use Functional\Gamification\Contracts\XpRule;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Services\Dto\XpAward;
use Functional\Health\Models\BodyMeasurement;
use Functional\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HealthMeasurementDayXpRule implements XpRule
{
    /**
     * Get the unique ledger key identifying the rule.
     */
    public function key(): string
    {
        return 'health_measurement_day';
    }

    /**
     * Get the domain the rule awards experience for.
     */
    public function domain(): GamificationDomain
    {
        return GamificationDomain::Health;
    }

    /**
     * Award a fixed entry per day holding at least one body measurement.
     *
     * @return Collection<int, XpAward>
     */
    public function awards(User $user, ?Carbon $since): Collection
    {
        $config = config('gamification.xp.health');

        return BodyMeasurement::query()
            ->where('user_id', $user->id)
            ->when($since, fn ($query) => $query->where('measured_at', '>=', $since->copy()->startOfDay()))
            ->get(['id', 'measured_at'])
            ->groupBy(fn (BodyMeasurement $measurement): string => $measurement->measured_at->toDateString())
            ->map(fn (Collection $measurements, string $day): XpAward => new XpAward(
                domain: $this->domain(),
                ruleKey: $this->key(),
                sourceType: 'period',
                sourceId: $day,
                points: $config['measurement_day'],
                occurredAt: Carbon::parse($day),
            ))
            ->values();
    }
}
