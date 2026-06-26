<?php

namespace Functional\Moto\Models;

use Functional\Moto\Database\Factories\MotoRideFactory;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Lomkit\Access\Controls\HasControl;

/**
 * @method static MotoRideFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $user_id
 * @property string $title
 * @property Carbon $started_at
 * @property int $duration
 * @property string $distance
 * @property string|null $weather_label
 * @property string|null $note
 */
#[UseFactory(MotoRideFactory::class)]
class MotoRide extends Model
{
    /** @use HasFactory<MotoRideFactory> */
    use HasControl, HasFactory, HasUlids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'started_at',
        'duration',
        'distance',
        'weather_label',
        'note',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'duration' => 'integer',
            'distance' => 'decimal:2',
        ];
    }

    /**
     * Get the user owning the moto ride.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
