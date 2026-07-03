<?php

namespace Functional\Gamification\Models;

use Functional\Gamification\Database\Factories\PlayerProfileFactory;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lomkit\Access\Controls\HasControl;

/**
 * @method static PlayerProfileFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $user_id
 * @property int $total_xp
 * @property int $level
 * @property Carbon|null $level_reached_at
 */
#[UseFactory(PlayerProfileFactory::class)]
class PlayerProfile extends Model
{
    /** @use HasFactory<PlayerProfileFactory> */
    use HasControl, HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'total_xp',
        'level',
        'level_reached_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_xp' => 'integer',
            'level' => 'integer',
            'level_reached_at' => 'datetime',
        ];
    }

    /**
     * Get the user owning the player profile.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
