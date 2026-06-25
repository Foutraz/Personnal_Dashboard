<?php

namespace Functional\Todo\Models;

use Functional\Todo\Database\Factories\TaskFactory;
use Functional\Todo\Enums\TaskPriority;
use Functional\Todo\Enums\TaskStatus;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Lomkit\Access\Controls\HasControl;

/**
 * @method static TaskFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $user_id
 * @property string $title
 * @property string|null $description
 * @property TaskPriority $priority
 * @property TaskStatus $status
 * @property Carbon|null $due_at
 * @property Carbon|null $completed_at
 * @property int $position
 */
#[UseFactory(TaskFactory::class)]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
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
        'priority',
        'status',
        'due_at',
        'completed_at',
        'position',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    /**
     * Get the user owning the task.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reminders attached to the task.
     *
     * @return HasMany<TaskReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(TaskReminder::class);
    }
}
