<?php

namespace Functional\Finance\Database\Factories;

use Functional\Finance\Enums\TransactionType;
use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentTransaction>
 */
class InvestmentTransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<InvestmentTransaction>
     */
    protected $model = InvestmentTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position_id' => Position::factory(),
            'user_id' => User::factory(),
            'type' => TransactionType::Buy,
            'quantity' => faker()->float(1, 20, 4),
            'unit_price' => faker()->float(10, 400, 2),
            'executed_at' => faker()->dateTime('-1 year', 'now'),
            'note' => faker()->boolean() ? faker()->words(6) : null,
        ];
    }

    /**
     * Indicate that the transaction is a sell.
     */
    public function sell(): static
    {
        return $this->state(fn (): array => [
            'type' => TransactionType::Sell,
        ]);
    }
}
