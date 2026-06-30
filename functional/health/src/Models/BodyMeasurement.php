<?php

namespace Functional\Health\Models;

use Functional\Health\Database\Factories\BodyMeasurementFactory;
use Functional\Health\Enums\MeasurementType;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Technical\Integrations\Models\IntegrationConnection;

/**
 * @method static BodyMeasurementFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $integration_connection_id
 * @property string $user_id
 * @property string $external_id
 * @property MeasurementType $type
 * @property float $value
 * @property string|null $unit
 * @property Carbon $measured_at
 * @property array<string, mixed>|null $raw
 */
#[UseFactory(BodyMeasurementFactory::class)]
class BodyMeasurement extends Model
{
    /** @use HasFactory<BodyMeasurementFactory> */
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'integration_connection_id',
        'user_id',
        'external_id',
        'type',
        'value',
        'unit',
        'measured_at',
        'raw',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MeasurementType::class,
            'value' => 'float',
            'measured_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    /**
     * Get the connection that produced the measurement.
     *
     * @return BelongsTo<IntegrationConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    /**
     * Get the user owning the measurement.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
