<?php

namespace Functional\Finance\Database\Factories;

use Functional\Finance\Enums\AssetType;
use Functional\Finance\Models\Position;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Position>
     */
    protected $model = Position::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $averageBuyPrice = faker()->float(10, 400, 2);

        return [
            'user_id' => User::factory(),
            'asset_symbol' => strtoupper(faker()->lowercase()->words(1)),
            'asset_name' => faker()->words(2),
            'asset_type' => faker()->randomElement(AssetType::cases()),
            'quantity' => faker()->float(1, 50, 4),
            'average_buy_price' => $averageBuyPrice,
            'current_price' => $averageBuyPrice * faker()->float(0.6, 1.6, 2),
            'currency' => 'EUR',
        ];
    }

    /**
     * Indicate that the position has no manually entered current price.
     */
    public function withoutCurrentPrice(): static
    {
        return $this->state(fn (): array => [
            'current_price' => null,
        ]);
    }
}
