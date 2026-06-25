<?php

namespace Functional\Sport\Database\Factories;

use Functional\Sport\Enums\SportType;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Technical\Integrations\Models\IntegrationConnection;

/**
 * @extends Factory<SportActivity>
 */
class SportActivityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SportActivity>
     */
    protected $model = SportActivity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();

        return [
            'integration_connection_id' => IntegrationConnection::factory()->for($user),
            'user_id' => $user,
            'strava_id' => faker()->unique()->number(1000000000, 9999999999),
            'name' => faker()->words(3),
            'sport_type' => faker()->randomElement(SportType::cases()),
            'distance' => faker()->float(1000, 50000),
            'moving_time' => faker()->number(600, 14400),
            'elapsed_time' => faker()->number(600, 18000),
            'total_elevation_gain' => faker()->float(0, 1500),
            'average_speed' => faker()->float(2, 12, 2),
            'max_speed' => faker()->float(4, 20, 2),
            'average_heartrate' => faker()->float(110, 165),
            'max_heartrate' => faker()->float(165, 195),
            'kilojoules' => faker()->float(100, 2500),
            'gear_id' => 'g'.faker()->number(1000000, 9999999),
            'map_polyline' => null,
            'started_at' => faker()->dateTime('-1 year', 'now'),
            'raw' => [],
        ];
    }
}
