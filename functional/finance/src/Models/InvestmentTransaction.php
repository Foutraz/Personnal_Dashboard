<?php

namespace Functional\Finance\Models;

use Functional\Finance\Database\Factories\InvestmentTransactionFactory;
use Functional\Finance\Enums\TransactionType;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Lomkit\Access\Controls\HasControl;

/**
 * @method static InvestmentTransactionFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $position_id
 * @property string $user_id
 * @property TransactionType $type
 * @property string $quantity
 * @property string $unit_price
 * @property Carbon $executed_at
 * @property string|null $note
 */
#[UseFactory(InvestmentTransactionFactory::class)]
class InvestmentTransaction extends Model
{
    /** @use HasFactory<InvestmentTransactionFactory> */
    use HasControl, HasFactory, HasUlids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'position_id',
        'user_id',
        'type',
        'quantity',
        'unit_price',
        'executed_at',
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
            'type' => TransactionType::class,
            'quantity' => 'decimal:8',
            'unit_price' => 'decimal:8',
            'executed_at' => 'datetime',
        ];
    }

    /**
     * Get the position owning the transaction.
     *
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Get the user owning the transaction.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
