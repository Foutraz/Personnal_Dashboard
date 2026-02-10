<?php

namespace Database\Factories;

use App\Enums\SportActivity\SyncStatus;
use App\Enums\SportActivity\Type;
use App\Models\SportActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SportActivity>
 */
class SportActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start_time = faker()->dateTime('-1 years');
        $duration = fake()->numberBetween(10, 240);
        $end_time = (clone $start_time)->modify('+'.$duration.' minutes');
        $avgHr = fake()->numberBetween(120, 160);
        $maxHr = fake()->numberBetween($avgHr + 10, min($avgHr + 40, 195));
        $minHr = fake()->numberBetween(90, $avgHr - 20);

        return [
            'provider' => 'Strava',
            'provider_id' => faker()->unique()->uuid(),
            'external_url' => faker()->url(),
            'type' => fake()->randomElement(Type::cases()),
            'libelle' => faker()->words(2),
            'description' => faker()->sentences(2),
            'start_time' => $start_time,
            'end_time' => $end_time,
            'timezone' => config('app.timezone'),
            'duration' => $duration,
            'moving_time' => fake()->numberBetween(10, $duration),
            'distance' => fake()->randomFloat(2, 1, 50),
            'average_speed' => fake()->randomFloat(2, 8, 14),
            'max_speed' => fake()->randomFloat(2, 14, 20),
            'elevation_gain' => fake()->numberBetween(100, 1000),
            'elevation_loss' => fake()->numberBetween(100, 1000),
            'max_altitude' => fake()->numberBetween(200, 1000),
            'min_altitude' => fake()->numberBetween(100, 900),
            'start_latitude' => faker()->latitude(),
            'start_longitude' => faker()->longitude(),
            'end_latitude' => faker()->latitude(),
            'end_longitude' => faker()->longitude(),
            'weather_condition' => fake()->randomElement(['rain', 'sun', 'snow', 'cloudy', 'night']),
            'temperature' => fake()->randomFloat(2, 5, 30),
            'average_heart_rate' => $avgHr,
            'max_heart_rate' => $maxHr,
            'min_heart_rate' => $minHr,
            'synced_at' => faker()->dateTime($end_time),
            'last_updated_from_provider_at' => faker()->dateTime('-1 weeks'),
            'sync_status' => fake()->randomElement(SyncStatus::cases()),
            'owner_id' => User::query()->inRandomOrder()->first() ?? User::factory(),
        ];
    }
}
