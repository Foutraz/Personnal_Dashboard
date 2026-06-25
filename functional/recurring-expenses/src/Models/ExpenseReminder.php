<?php

namespace Functional\RecurringExpenses\Models;

use Functional\RecurringExpenses\Database\Factories\ExpenseReminderFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @method static ExpenseReminderFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $recurring_expense_id
 * @property Carbon $due_at
 * @property bool $notified
 */
#[UseFactory(ExpenseReminderFactory::class)]
class ExpenseReminder extends Model
{
    /** @use HasFactory<ExpenseReminderFactory> */
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'recurring_expense_id',
        'due_at',
        'notified',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'notified' => 'boolean',
        ];
    }

    /**
     * Get the recurring expense owning the reminder.
     *
     * @return BelongsTo<RecurringExpense, $this>
     */
    public function recurringExpense(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class);
    }
}
