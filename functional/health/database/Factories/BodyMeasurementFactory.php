<?php

namespace Functional\Health\Database\Factories;

use Functional\Health\Enums\MeasurementType;
use Functional\Health\Models\BodyMeasurement;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Technical\Integrations\Models\IntegrationConnection;

/**
 * @extends Factory<BodyMeasurement>
 */
class BodyMeasurementFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<BodyMeasurement>
     */
    protected $model = BodyMeasurement::class;

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
            'type' => faker()->randomElement(MeasurementType::cases()),
            'value' => faker()->float(50, 120, 2),
            'unit' => null,
            'measured_at' => faker()->dateTime('-1 year', 'now'),
            'raw' => [],
        ];
    }
}
