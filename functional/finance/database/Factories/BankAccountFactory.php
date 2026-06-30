<?php

namespace Functional\Finance\Database\Factories;

use Functional\Finance\Models\BankAccount;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Technical\Integrations\Models\IntegrationConnection;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<BankAccount>
     */
    protected $model = BankAccount::class;

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
            'external_id' => (string) faker()->unique()->number(100000, 999999),
            'institution_id' => 'INST_'.faker()->uppercase()->words(1),
            'name' => faker()->words(2),
            'iban' => 'FR'.faker()->number(10000000, 99999999),
            'currency' => 'EUR',
            'balance' => faker()->float(100, 10000, 2),
            'balance_at' => faker()->dateTime('-1 day', 'now'),
            'raw' => [],
        ];
    }
}
