<?php

namespace Functional\Finance\Database\Factories;

use Functional\Finance\Models\BankAccount;
use Functional\Finance\Models\BankTransaction;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankTransaction>
 */
class BankTransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<BankTransaction>
     */
    protected $model = BankTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_account_id' => BankAccount::factory(),
            'user_id' => User::factory(),
            'external_id' => (string) faker()->unique()->number(100000, 999999),
            'amount' => faker()->float(-500, 500, 2),
            'currency' => 'EUR',
            'booked_at' => faker()->dateTime('-1 year', 'now'),
            'description' => faker()->words(4),
            'counterparty' => faker()->company(),
            'raw' => [],
        ];
    }
}
