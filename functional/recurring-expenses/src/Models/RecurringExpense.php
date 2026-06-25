<?php

namespace Functional\RecurringExpenses\Models;

use Functional\RecurringExpenses\Database\Factories\RecurringExpenseFactory;
use Functional\RecurringExpenses\Enums\ExpenseCategory;
use Functional\RecurringExpenses\Enums\ExpenseFrequency;
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
 * @method static RecurringExpenseFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $user_id
 * @property string $label
 * @property string $amount
 * @property string $currency
 * @property ExpenseCategory $category
 * @property ExpenseFrequency $frequency
 * @property int|null $due_day
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon $next_due_at
 * @property bool $active
 * @property string|null $note
 */
#[UseFactory(RecurringExpenseFactory::class)]
class RecurringExpense extends Model
{
    /** @use HasFactory<RecurringExpenseFactory> */
    use HasControl, HasFactory, HasUlids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'label',
        'amount',
        'currency',
        'category',
        'frequency',
        'due_day',
        'starts_at',
        'ends_at',
        'next_due_at',
        'active',
        'note',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'category' => ExpenseCategory::class,
            'frequency' => ExpenseFrequency::class,
            'due_day' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'next_due_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    /**
     * Get the user owning the recurring expense.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reminders attached to the recurring expense.
     *
     * @return HasMany<ExpenseReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(ExpenseReminder::class);
    }

    /**
     * Determine whether the recurring expense has reached its end date.
     */
    public function hasEnded(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }
}
