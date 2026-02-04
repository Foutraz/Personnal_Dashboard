<?php

namespace App\Models;

use App\Enums\SportActivity\SyncStatus;
use App\Enums\SportActivity\Type;
use Database\Factories\SportActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SportActivity extends Model
{
    /** @use HasFactory<SportActivityFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'provider_id',
        'external_url',
        'type',
        'libelle',
        'description',
        'start_time',
        'end_time',
        'timezone',
        'duration',
        'moving_time',
        'distance',
        'average_speed',
        'max_speed',
        'elevation_gain',
        'elevation_loss',
        'max_altitude',
        'min_altitude',
        'start_latitude',
        'start_longitude',
        'end_latitude',
        'end_longitude',
        'weather_condition',
        'temperature',
        'average_heart_rate',
        'max_heart_rate',
        'min_heart_rate',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'synced_at',
        'last_updated_from_provider_at',
        'sync_status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => Type::class,
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'duration' => 'integer',
            'moving_time' => 'integer',
            'distance' => 'float',
            'average_speed' => 'float',
            'max_speed' => 'float',
            'elevation_gain' => 'integer',
            'elevation_loss' => 'integer',
            'max_altitude' => 'integer',
            'min_altitude' => 'integer',
            'start_latitude' => 'decimal(9,6)',
            'start_longitude' => 'decimal(9,6)',
            'end_latitude' => 'decimal(9,6)',
            'end_longitude' => 'decimal(9,6)',
            'temperature' => 'float',
            'average_heart_rate' => 'float',
            'max_heart_rate' => 'float',
            'min_heart_rate' => 'float',
            'synced_at' => 'datetime',
            'last_updated_from_provider_at' => 'datetime',
            'sync_status' => SyncStatus::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
