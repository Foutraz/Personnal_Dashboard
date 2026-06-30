<?php

namespace Functional\Finance\Models;

use Functional\Finance\Database\Factories\BankAccountFactory;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Technical\Integrations\Models\IntegrationConnection;

/**
 * @method static BankAccountFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $integration_connection_id
 * @property string $user_id
 * @property string $external_id
 * @property string|null $institution_id
 * @property string|null $name
 * @property string|null $iban
 * @property string $currency
 * @property float $balance
 * @property Carbon|null $balance_at
 * @property array<string, mixed>|null $raw
 */
#[UseFactory(BankAccountFactory::class)]
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'integration_connection_id',
        'user_id',
        'external_id',
        'institution_id',
        'name',
        'iban',
        'currency',
        'balance',
        'balance_at',
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
            'balance' => 'decimal:2',
            'balance_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    /**
     * Get the integration connection that owns the bank account.
     *
     * @return BelongsTo<IntegrationConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    /**
     * Get the user owning the bank account.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the transactions belonging to the bank account.
     *
     * @return HasMany<BankTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
