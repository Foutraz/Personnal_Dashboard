<?php

namespace Tests\Feature\Health;

use Functional\Health\Actions\UpsertBodyMeasurement;
use Functional\Health\Enums\MeasurementType;
use Functional\Health\Models\BodyMeasurement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class BodyMeasurementUpsertTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_body_measurement_on_first_upsert(): void
    {
        $connection = IntegrationConnection::factory()->create(['provider' => IntegrationProvider::Withings]);

        (new UpsertBodyMeasurement)(
            connection: $connection,
            externalId: '111',
            type: MeasurementType::Weight,
            value: 70.5,
            measuredAt: now(),
        );

        $this->assertSame(1, BodyMeasurement::query()->count());
    }

    #[Test]
    public function it_updates_value_on_second_upsert_with_same_key(): void
    {
        $connection = IntegrationConnection::factory()->create(['provider' => IntegrationProvider::Withings]);

        (new UpsertBodyMeasurement)(
            connection: $connection,
            externalId: '111',
            type: MeasurementType::Weight,
            value: 70.5,
            measuredAt: now(),
        );

        (new UpsertBodyMeasurement)(
            connection: $connection,
            externalId: '111',
            type: MeasurementType::Weight,
            value: 71.2,
            measuredAt: now(),
        );

        $this->assertSame(1, BodyMeasurement::query()->count());
        $this->assertEqualsWithDelta(71.2, BodyMeasurement::query()->first()->value, 0.001);
    }
}
