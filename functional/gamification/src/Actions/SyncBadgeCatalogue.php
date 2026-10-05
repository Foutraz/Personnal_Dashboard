<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Exceptions\InvalidBadgeThresholdException;
use Functional\Gamification\Exceptions\InvalidBadgeTierXpException;
use Functional\Gamification\Exceptions\MissingBadgeThresholdException;
use Functional\Gamification\Exceptions\NonIncreasingBadgeThresholdsException;
use Functional\Gamification\Models\Badge;
use Illuminate\Support\Collection;

class SyncBadgeCatalogue
{
    /**
     * Upsert one badge per tagged rule and tier by key, leaving badges of rules that are no longer tagged untouched.
     *
     * @throws MissingBadgeThresholdException|InvalidBadgeThresholdException|NonIncreasingBadgeThresholdsException|InvalidBadgeTierXpException
     */
    public function handle(): void
    {
        $rows = collect(app()->tagged(BadgeRule::TAG))
            ->flatMap(fn (BadgeRule $rule): Collection => $this->rowsOf($rule))
            ->all();

        Badge::query()->upsert(
            $rows,
            ['key'],
            ['rule_key', 'domain', 'tier', 'threshold', 'xp_reward'],
        );
    }

    /**
     * @return Collection<int, array{key: string, rule_key: string, domain: string, tier: string, threshold: int|float|string, xp_reward: int}>
     *
     * @throws MissingBadgeThresholdException|InvalidBadgeThresholdException|NonIncreasingBadgeThresholdsException|InvalidBadgeTierXpException
     */
    private function rowsOf(BadgeRule $rule): Collection
    {
        $rows = collect(BadgeTier::cases())
            ->sortBy(fn (BadgeTier $tier): int => $tier->rank())
            ->map(fn (BadgeTier $tier): array => $this->row($rule, $tier))
            ->values();

        $thresholdsByTier = $rows->pluck('threshold', 'tier');

        $isStrictlyIncreasing = $thresholdsByTier->values()->sliding(2)->every(fn (Collection $pair): bool => (float) $pair->first() < (float) $pair->last());

        if (! $isStrictlyIncreasing) {
            throw new NonIncreasingBadgeThresholdsException($rule->key(), $thresholdsByTier->all());
        }

        return $rows;
    }

    /**
     * @return array{key: string, rule_key: string, domain: string, tier: string, threshold: int|float|string, xp_reward: int}
     *
     * @throws MissingBadgeThresholdException|InvalidBadgeThresholdException|InvalidBadgeTierXpException
     */
    private function row(BadgeRule $rule, BadgeTier $tier): array
    {
        $threshold = config($rule->key()->thresholdConfigPath($tier));

        if ($threshold === null) {
            throw new MissingBadgeThresholdException($rule->key(), $tier);
        }

        if (! is_numeric($threshold)) {
            throw new InvalidBadgeThresholdException($rule->key(), $tier, $threshold);
        }

        return [
            'key' => $rule->key()->badgeKey($tier),
            'rule_key' => $rule->key()->value,
            'domain' => $rule->domain()->value,
            'tier' => $tier->value,
            'threshold' => $threshold,
            'xp_reward' => $tier->xpReward(),
        ];
    }
}
