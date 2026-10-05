<?php

namespace Functional\Gamification\Models;

use Functional\Gamification\Challenges\States\ChallengeState;
use Functional\Gamification\Challenges\States\ChallengeStateFactory;
use Functional\Gamification\Database\Factories\ChallengeFactory;
use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Goals\Enums\GoalMetric;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lomkit\Access\Controls\HasControl;

/**
 * @method static ChallengeFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $user_id
 * @property string $week_key
 * @property ChallengeTemplateKey $template_key
 * @property GamificationDomain $domain
 * @property GoalMetric $metric
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $baseline_value
 * @property string $target_value
 * @property string $current_value
 * @property int $xp_reward
 * @property ChallengeStatus $status
 * @property Carbon|null $accepted_at
 * @property Carbon|null $resolved_at
 * @property Carbon $updated_at
 */
#[UseFactory(ChallengeFactory::class)]
class Challenge extends Model
{
    /** @use HasFactory<ChallengeFactory> */
    use HasControl, HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'week_key',
        'template_key',
        'domain',
        'metric',
        'starts_at',
        'ends_at',
        'baseline_value',
        'target_value',
        'current_value',
        'xp_reward',
        'status',
        'accepted_at',
        'resolved_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_key' => ChallengeTemplateKey::class,
            'domain' => GamificationDomain::class,
            'metric' => GoalMetric::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'baseline_value' => 'decimal:2',
            'target_value' => 'decimal:2',
            'current_value' => 'decimal:2',
            'xp_reward' => 'integer',
            'status' => ChallengeStatus::class,
            'accepted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Get the user owning the challenge.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function state(): ChallengeState
    {
        return ChallengeStateFactory::fromStatus($this->status);
    }
}
