<?php

namespace Functional\Moto\Database\Factories;

use Functional\Moto\Models\MotoRide;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use WeakMap;

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

    public function configure(): static
    {
        $intendedRecordings = new WeakMap;

        return $this
            ->afterMaking(function (MotoRide $ride) use ($intendedRecordings): void {
                $intendedRecordings[$ride] = $ride->getAttributes()['recorded_at'];
            })
            ->afterCreating(function (MotoRide $ride) use ($intendedRecordings): void {
                $recordedAt = $intendedRecordings[$ride];

                MotoRide::withTrashed()->whereKey($ride->getKey())->toBase()->update(['recorded_at' => $recordedAt]);

                $ride->forceFill(['recorded_at' => $recordedAt])->syncOriginalAttribute('recorded_at');
            });
    }

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
            'created_at' => fn (array $attributes): mixed => $attributes['started_at'],
            'recorded_at' => fn (array $attributes): mixed => $attributes['started_at'],
            'updated_at' => fn (array $attributes): mixed => $attributes['started_at'],
        ];
    }
}
