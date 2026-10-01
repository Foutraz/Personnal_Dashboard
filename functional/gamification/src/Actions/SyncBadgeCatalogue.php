<?php

namespace Functional\Gamification\Actions;

use Functional\Gamification\Contracts\BadgeRule;
use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Exceptions\MissingBadgeThresholdException;
use Functional\Gamification\Models\Badge;
use Illuminate\Support\Collection;

class SyncBadgeCatalogue
{
    /**
     * Upsert one badge per tagged rule and tier by key, leaving badges of rules that are no longer tagged untouched.
     *
     * @throws MissingBadgeThresholdException
     */
    public function handle(): void
    {
        $rows = collect(app()->tagged('gamification.badge_rules'))
            ->flatMap(fn (BadgeRule $rule): Collection => collect(BadgeTier::cases())
                ->map(fn (BadgeTier $tier): array => $this->row($rule, $tier)))
            ->all();

        Badge::query()->upsert(
            $rows,
            ['key'],
            ['rule_key', 'domain', 'tier', 'threshold', 'xp_reward'],
        );
    }

    /**
     * @return array{key: string, rule_key: string, domain: string, tier: string, threshold: int|float|string, xp_reward: int}
     *
     * @throws MissingBadgeThresholdException
     */
    private function row(BadgeRule $rule, BadgeTier $tier): array
    {
        $threshold = config("gamification.badges.thresholds.{$rule->key()}.{$tier->value}");

        if ($threshold === null) {
            throw new MissingBadgeThresholdException($rule->key(), $tier);
        }

        return [
            'key' => "{$rule->key()}_{$tier->value}",
            'rule_key' => $rule->key(),
            'domain' => $rule->domain()->value,
            'tier' => $tier->value,
            'threshold' => $threshold,
            'xp_reward' => $tier->xpReward(),
        ];
    }
}
