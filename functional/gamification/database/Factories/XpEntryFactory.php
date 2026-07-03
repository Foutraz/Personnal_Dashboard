<?php

namespace Functional\Gamification\Database\Factories;

use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\XpEntry;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<XpEntry>
 */
class XpEntryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<XpEntry>
     */
    protected $model = XpEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'domain' => faker()->randomElement(GamificationDomain::cases()),
            'rule_key' => faker()->randomElement(['sport_activity', 'todo_task_completed', 'health_measurement_day']),
            'source_type' => 'test-source',
            'source_id' => strtolower((string) Str::ulid()),
            'points' => faker()->number(1, 60),
            'occurred_at' => faker()->dateTime('-6 months', 'now'),
        ];
    }
}
