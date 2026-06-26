<?php

namespace Functional\Planning\Models;

use Functional\Planning\Database\Factories\CalendarEventFactory;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Lomkit\Access\Controls\HasControl;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;

/**
 * @method static CalendarEventFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $integration_connection_id
 * @property string $user_id
 * @property IntegrationProvider $provider
 * @property string $external_id
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property bool $all_day
 * @property string|null $external_link
 * @property array<string, mixed>|null $raw
 */
#[UseFactory(CalendarEventFactory::class)]
class CalendarEvent extends Model
{
    /** @use HasFactory<CalendarEventFactory> */
    use HasControl, HasFactory, HasUlids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'integration_connection_id',
        'user_id',
        'provider',
        'external_id',
        'title',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'all_day',
        'external_link',
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
            'provider' => IntegrationProvider::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'raw' => 'array',
        ];
    }

    /**
     * Get the connection that produced the event.
     *
     * @return BelongsTo<IntegrationConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    /**
     * Get the user owning the event.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
