<?php

namespace Functional\Exploration\Database\Factories;

use Functional\Exploration\Models\ExploredCell;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExploredCell>
 */
class ExploredCellFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ExploredCell>
     */
    protected $model = ExploredCell::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lat = faker()->float(43, 49, 4);
        $lng = faker()->float(1, 7, 4);
        $x = (int) floor($lng / 0.01);
        $y = (int) floor($lat / 0.01);

        return [
            'user_id' => User::factory(),
            'cell_key' => $x.':'.$y,
            'lat' => $lat,
            'lng' => $lng,
            'visit_count' => faker()->number(1, 12),
            'first_seen_at' => faker()->dateTime('-1 year', '-6 months'),
            'last_seen_at' => faker()->dateTime('-6 months', 'now'),
        ];
    }
}
