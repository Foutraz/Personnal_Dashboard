<?php

namespace Functional\Gamification\Database\Factories;

use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\Streak;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Streak>
 */
class StreakFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Streak>
     */
    protected $model = Streak::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $current = faker()->number(1, 30);

        return [
            'user_id' => User::factory(),
            'domain' => faker()->randomElement(GamificationDomain::cases()),
            'current_count' => $current,
            'best_count' => $current + faker()->number(0, 40),
            'last_activity_date' => now()->subDays(faker()->number(0, 5))->toDateString(),
        ];
    }
}
