<?php

namespace Functional\Gamification\Services;

use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Models\Badge;
use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Services\Dto\BadgeFamilyProgress;
use Functional\Gamification\Services\Dto\BadgeMedal;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BadgeShowcase
{
    /**
     * Build the progress of every tagged badge family present in the catalogue, measuring each rule once.
     *
     * @return Collection<int, BadgeFamilyProgress>
     */
    public function families(User $user): Collection
    {
        $rules = $this->rules();
        $catalogue = Badge::query()->whereIn('rule_key', $this->keysOf($rules))->get()->groupBy('rule_key');
        $ownedBadgeIds = BadgeAward::query()->whereBelongsTo($user)->pluck('badge_id')->flip();

        return $rules
            ->filter(fn (BadgeRule $rule): bool => $catalogue->has($rule->key()->value))
            ->map(fn (BadgeRule $rule): BadgeFamilyProgress => $this->family($rule, $catalogue->get($rule->key()->value), $ownedBadgeIds, $rule->measure($user)))
            ->values();
    }

    /**
     * Count the badges of the tagged families the user has earned.
     */
    public function earnedCount(User $user): int
    {
        return BadgeAward::query()
            ->whereBelongsTo($user)
            ->whereHas('badge', fn (Builder $badge) => $badge->whereIn('rule_key', $this->keysOf($this->rules())))
            ->count();
    }

    /**
     * Count the catalogue badges of the tagged families.
     */
    public function totalCount(): int
    {
        return Badge::query()->whereIn('rule_key', $this->keysOf($this->rules()))->count();
    }

    /**
     * @param  Collection<int, Badge>  $badges
     * @param  Collection<string, int>  $ownedBadgeIds
     */
    private function family(BadgeRule $rule, Collection $badges, Collection $ownedBadgeIds, float $measure): BadgeFamilyProgress
    {
        $tiers = $badges->sortBy(fn (Badge $badge): int => $badge->tier->rank())->values();
        $next = $tiers->first(fn (Badge $badge): bool => ! $ownedBadgeIds->has($badge->id));

        return new BadgeFamilyProgress(
            ruleKey: $rule->key(),
            name: $rule->key()->label(),
            domain: $rule->domain(),
            unit: $rule->unit(),
            medals: $tiers
                ->map(fn (Badge $badge): BadgeMedal => BadgeMedal::fromBadge($badge, $rule->unit(), $ownedBadgeIds->has($badge->id), $measure))
                ->all(),
            currentValue: $measure,
            nextThreshold: $next === null ? null : (float) $next->threshold,
            percentage: $next === null ? 100.0 : $this->percentage($measure, $this->floorBefore($tiers, $next), (float) $next->threshold),
        );
    }

    /**
     * @param  Collection<int, Badge>  $tiers
     */
    private function floorBefore(Collection $tiers, Badge $next): float
    {
        $previous = $tiers->last(fn (Badge $badge): bool => $badge->tier->rank() < $next->tier->rank());

        return $previous === null ? 0.0 : (float) $previous->threshold;
    }

    private function percentage(float $measure, float $floor, float $ceiling): float
    {
        $span = max($ceiling - $floor, PHP_FLOAT_EPSILON);

        return min(max(($measure - $floor) / $span * 100, 0.0), 100.0);
    }

    /**
     * @return Collection<int, BadgeRule>
     */
    private function rules(): Collection
    {
        return collect(app()->tagged(BadgeRule::TAG));
    }

    /**
     * @param  Collection<int, BadgeRule>  $rules
     * @return array<int, string>
     */
    private function keysOf(Collection $rules): array
    {
        return $rules->map(fn (BadgeRule $rule): string => $rule->key()->value)->all();
    }
}
