<?php

namespace Functional\Moto\Database\Factories;

use Functional\Moto\Models\MotoRide;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MotoRide>
 */
class MotoRideFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<MotoRide>
     */
    protected $model = MotoRide::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => faker()->words(3),
            'started_at' => now()->subDays(faker()->number(1, 90)),
            'duration' => faker()->number(900, 21600),
            'distance' => faker()->float(10, 450, 2),
            'weather_label' => faker()->randomElement(['Excellent', 'Bon', 'Moyen', null]),
            'note' => faker()->boolean() ? faker()->words(8) : null,
        ];
    }
}
