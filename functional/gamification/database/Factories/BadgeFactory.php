<?php

namespace Functional\Gamification\Database\Factories;

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
        $ruleKey = faker()->randomElement(array_keys(config('gamification.badges.thresholds')));
        $tier = faker()->randomElement(BadgeTier::cases());

        return [
            'key' => "{$ruleKey}_{$tier->value}_".Str::lower(Str::random(6)),
            'rule_key' => $ruleKey,
            'domain' => faker()->randomElement(GamificationDomain::cases()),
            'tier' => $tier,
            'threshold' => config("gamification.badges.thresholds.{$ruleKey}.{$tier->value}"),
            'xp_reward' => $tier->xpReward(),
        ];
    }
}
