<?php

namespace Functional\Health\Actions;

use Functional\Health\Enums\MeasurementType;
use Functional\Health\Models\BodyMeasurement;
use Illuminate\Support\Carbon;
use Technical\Integrations\Models\IntegrationConnection;

class UpsertBodyMeasurement
{
    /**
     * Idempotently persist a body measurement for the given connection.
     *
     * @param  array<string, mixed>|null  $raw
     */
    public function __invoke(
        IntegrationConnection $connection,
        string $externalId,
        MeasurementType $type,
        float $value,
        Carbon $measuredAt,
        ?string $unit = null,
        ?array $raw = null,
    ): BodyMeasurement {
        return BodyMeasurement::query()->updateOrCreate(
            [
                'integration_connection_id' => $connection->id,
                'external_id' => $externalId,
                'type' => $type,
            ],
            [
                'user_id' => $connection->user_id,
                'value' => $value,
                'unit' => $unit,
                'measured_at' => $measuredAt,
                'raw' => $raw,
            ]
        );
    }
}
