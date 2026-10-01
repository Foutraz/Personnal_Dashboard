<?php

namespace Functional\Gamification\Database\Factories;

use Functional\Gamification\Models\Badge;
use Functional\Gamification\Models\BadgeAward;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BadgeAward>
 */
class BadgeAwardFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<BadgeAward>
     */
    protected $model = BadgeAward::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'badge_id' => Badge::factory(),
            'awarded_at' => now(),
        ];
    }
}
