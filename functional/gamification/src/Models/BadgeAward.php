<?php

namespace Functional\Gamification\Models;

use Functional\Gamification\Database\Factories\BadgeAwardFactory;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lomkit\Access\Controls\HasControl;

/**
 * @method static BadgeAwardFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $user_id
 * @property string $badge_id
 * @property Carbon $awarded_at
 */
#[UseFactory(BadgeAwardFactory::class)]
class BadgeAward extends Model
{
    /** @use HasFactory<BadgeAwardFactory> */
    use HasControl, HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'badge_id',
        'awarded_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'awarded_at' => 'datetime',
        ];
    }

    /**
     * Get the badge that was awarded.
     *
     * @return BelongsTo<Badge, $this>
     */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    /**
     * Get the user owning the award.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
