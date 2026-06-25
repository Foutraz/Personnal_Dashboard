<?php

namespace Functional\Goals\Models;

use Functional\Goals\Database\Factories\GoalFactory;
use Functional\Goals\Enums\GoalMetric;
use Functional\Goals\Enums\GoalStatus;
use Functional\Goals\Enums\GoalType;
use Functional\Goals\Services\GoalProgressCalculator;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Lomkit\Access\Controls\HasControl;

/**
 * @method static GoalFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $user_id
 * @property string $title
 * @property string|null $description
 * @property GoalType $type
 * @property GoalMetric $metric
 * @property string $target_value
 * @property string|null $manual_current_value
 * @property string|null $unit
 * @property Carbon|null $starts_at
 * @property Carbon|null $deadline
 * @property GoalStatus $status
 */
#[UseFactory(GoalFactory::class)]
class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasControl, HasFactory, HasUlids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'type',
        'metric',
        'target_value',
        'manual_current_value',
        'unit',
        'starts_at',
        'deadline',
        'status',
    ];

    /**
     * The computed accessors appended to the serialized model.
     *
     * @var list<string>
     */
    protected $appends = [
        'current_value',
        'progress_percentage',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => GoalType::class,
            'metric' => GoalMetric::class,
            'target_value' => 'decimal:4',
            'manual_current_value' => 'decimal:4',
            'starts_at' => 'datetime',
            'deadline' => 'datetime',
            'status' => GoalStatus::class,
        ];
    }

    /**
     * Get the user owning the goal.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine whether the goal already reached its achieved status.
     */
    public function isAchieved(): bool
    {
        return $this->status === GoalStatus::Achieved;
    }

    /**
     * Expose the computed current value of the goal for its metric.
     *
     * @return Attribute<float, never>
     */
    protected function currentValue(): Attribute
    {
        return Attribute::get(fn (): float => app(GoalProgressCalculator::class)->currentValue($this));
    }

    /**
     * Expose the computed progress percentage of the goal against its target.
     *
     * @return Attribute<float, never>
     */
    protected function progressPercentage(): Attribute
    {
        return Attribute::get(fn (): float => app(GoalProgressCalculator::class)->progress($this)->percentage);
    }
}
