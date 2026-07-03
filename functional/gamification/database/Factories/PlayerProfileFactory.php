<?php

namespace Functional\Gamification\Database\Factories;

use Functional\Gamification\Models\PlayerProfile;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerProfile>
 */
class PlayerProfileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PlayerProfile>
     */
    protected $model = PlayerProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'total_xp' => faker()->number(0, 20000),
            'level' => faker()->number(1, 20),
            'level_reached_at' => faker()->dateTime('-3 months', 'now'),
        ];
    }
}
