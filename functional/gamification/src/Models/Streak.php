<?php

namespace Functional\Gamification\Models;

use Functional\Gamification\Database\Factories\StreakFactory;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lomkit\Access\Controls\HasControl;

/**
 * @method static StreakFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $user_id
 * @property GamificationDomain $domain
 * @property int $current_count
 * @property int $best_count
 * @property Carbon|null $last_activity_date
 */
#[UseFactory(StreakFactory::class)]
class Streak extends Model
{
    /** @use HasFactory<StreakFactory> */
    use HasControl, HasFactory, HasUlids;

    public const MILESTONE_RULE_KEY = 'streak_milestone';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'domain',
        'current_count',
        'best_count',
        'last_activity_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'domain' => GamificationDomain::class,
            'current_count' => 'integer',
            'best_count' => 'integer',
            'last_activity_date' => 'date',
        ];
    }

    /**
     * Get the user owning the streak.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
