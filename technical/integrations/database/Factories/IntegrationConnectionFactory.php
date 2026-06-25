<?php

namespace Technical\Integrations\Database\Factories;

use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;

/**
 * @extends Factory<IntegrationConnection>
 */
class IntegrationConnectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<IntegrationConnection>
     */
    protected $model = IntegrationConnection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => faker()->randomElement(IntegrationProvider::cases()),
            'access_token' => faker()->sha256(),
            'refresh_token' => faker()->sha256(),
            'expires_at' => faker()->dateTime('+1 hour', '+6 hours'),
            'scopes' => ['read', 'activity:read_all'],
            'external_id' => (string) faker()->number(10000000, 99999999),
            'meta' => [],
        ];
    }

    /**
     * Indicate that the connection's access token has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => faker()->dateTime('-6 hours', '-1 hour'),
        ]);
    }
}
