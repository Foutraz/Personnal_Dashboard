<?php

namespace Functional\Gamification\Models;

use Functional\Gamification\Database\Factories\XpEntryFactory;
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
 * @method static XpEntryFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $user_id
 * @property GamificationDomain $domain
 * @property string $rule_key
 * @property string $source_type
 * @property string $source_id
 * @property int $points
 * @property Carbon $occurred_at
 */
#[UseFactory(XpEntryFactory::class)]
class XpEntry extends Model
{
    /** @use HasFactory<XpEntryFactory> */
    use HasControl, HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'domain',
        'rule_key',
        'source_type',
        'source_id',
        'points',
        'occurred_at',
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
            'points' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Get the user owning the xp entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
