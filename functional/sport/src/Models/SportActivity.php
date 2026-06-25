<?php

namespace Functional\Sport\Models;

use Functional\Sport\Database\Factories\SportActivityFactory;
use Functional\Sport\Enums\SportType;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Lomkit\Access\Controls\HasControl;
use Technical\Integrations\Models\IntegrationConnection;

/**
 * @method static SportActivityFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $integration_connection_id
 * @property string $user_id
 * @property int $strava_id
 * @property string $name
 * @property SportType $sport_type
 * @property float $distance
 * @property int $moving_time
 * @property int $elapsed_time
 * @property float $total_elevation_gain
 * @property float|null $average_speed
 * @property float|null $max_speed
 * @property float|null $average_heartrate
 * @property float|null $max_heartrate
 * @property float|null $kilojoules
 * @property string|null $gear_id
 * @property string|null $map_polyline
 * @property Carbon $started_at
 * @property array<string, mixed>|null $raw
 */
#[UseFactory(SportActivityFactory::class)]
class SportActivity extends Model
{
    /** @use HasFactory<SportActivityFactory> */
    use HasControl, HasFactory, HasUlids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'integration_connection_id',
        'user_id',
        'strava_id',
        'name',
        'sport_type',
        'distance',
        'moving_time',
        'elapsed_time',
        'total_elevation_gain',
        'average_speed',
        'max_speed',
        'average_heartrate',
        'max_heartrate',
        'kilojoules',
        'gear_id',
        'map_polyline',
        'started_at',
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
            'sport_type' => SportType::class,
            'distance' => 'float',
            'moving_time' => 'integer',
            'elapsed_time' => 'integer',
            'total_elevation_gain' => 'float',
            'average_speed' => 'float',
            'max_speed' => 'float',
            'average_heartrate' => 'float',
            'max_heartrate' => 'float',
            'kilojoules' => 'float',
            'started_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    /**
     * Get the connection that produced the activity.
     *
     * @return BelongsTo<IntegrationConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    /**
     * Get the user owning the activity.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
