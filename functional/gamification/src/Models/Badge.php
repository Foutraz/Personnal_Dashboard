<?php

namespace Functional\Gamification\Models;

use Functional\Gamification\Database\Factories\BadgeFactory;
use Functional\Gamification\Enums\BadgeTier;
use Functional\Gamification\Enums\GamificationDomain;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Number;
use Lomkit\Access\Controls\HasControl;

/**
 * @method static BadgeFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $key
 * @property string $rule_key
 * @property GamificationDomain $domain
 * @property BadgeTier $tier
 * @property string $threshold
 * @property int $xp_reward
 */
#[UseFactory(BadgeFactory::class)]
class Badge extends Model
{
    /** @use HasFactory<BadgeFactory> */
    use HasControl, HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'rule_key',
        'domain',
        'tier',
        'threshold',
        'xp_reward',
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
            'tier' => BadgeTier::class,
            'threshold' => 'decimal:2',
            'xp_reward' => 'integer',
        ];
    }

    /**
     * Get the awards granted for the badge.
     *
     * @return HasMany<BadgeAward, $this>
     */
    public function awards(): HasMany
    {
        return $this->hasMany(BadgeAward::class);
    }

    /**
     * Get the translated name of the badge family.
     */
    public function name(): string
    {
        return __("gamification::badges.rules.{$this->rule_key}.name");
    }

    /**
     * Get the translated description of the badge with its threshold.
     */
    public function description(): string
    {
        return __("gamification::badges.rules.{$this->rule_key}.description", [
            'threshold' => Number::format((float) $this->threshold, locale: app()->getLocale()),
        ]);
    }
}
