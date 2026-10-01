<?php

namespace Functional\Gamification\Database\Factories;

use Functional\Gamification\Enums\BadgeRuleKey;
use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\Badge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Badge>
 */
class BadgeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Badge>
     */
    protected $model = Badge::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ruleKey = faker()->randomElement(BadgeRuleKey::cases());
        $tier = faker()->randomElement(BadgeTier::cases());

        return [
            'key' => $ruleKey->badgeKey($tier).'_'.Str::lower(Str::random(6)),
            'rule_key' => $ruleKey->value,
            'domain' => faker()->randomElement(GamificationDomain::cases()),
            'tier' => $tier,
            'threshold' => config($ruleKey->thresholdConfigPath($tier)),
            'xp_reward' => $tier->xpReward(),
        ];
    }
}
