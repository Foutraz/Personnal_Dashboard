<?php

namespace Functional\Finance\Models;

use Functional\Finance\Database\Factories\BankTransactionFactory;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @method static BankTransactionFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $bank_account_id
 * @property string $user_id
 * @property string $external_id
 * @property float $amount
 * @property string $currency
 * @property Carbon $booked_at
 * @property string|null $description
 * @property string|null $counterparty
 * @property array<string, mixed>|null $raw
 */
#[UseFactory(BankTransactionFactory::class)]
class BankTransaction extends Model
{
    /** @use HasFactory<BankTransactionFactory> */
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'bank_account_id',
        'user_id',
        'external_id',
        'amount',
        'currency',
        'booked_at',
        'description',
        'counterparty',
        'raw',
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
            'booked_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    /**
     * Get the bank account owning the transaction.
     *
     * @return BelongsTo<BankAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
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
