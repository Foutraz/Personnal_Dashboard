<?php

namespace Functional\Todo\Models;

use Functional\Todo\Database\Factories\TaskReminderFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @method static TaskReminderFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $task_id
 * @property Carbon $remind_at
 * @property bool $sent
 */
#[UseFactory(TaskReminderFactory::class)]
class TaskReminder extends Model
{
    /** @use HasFactory<TaskReminderFactory> */
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'remind_at',
        'sent',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'sent' => 'boolean',
        ];
    }

    /**
     * Get the task owning the reminder.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
