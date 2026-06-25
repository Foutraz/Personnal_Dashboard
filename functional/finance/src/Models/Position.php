<?php

namespace Functional\Finance\Models;

use Functional\Finance\Database\Factories\PositionFactory;
use Functional\Finance\Enums\AssetType;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lomkit\Access\Controls\HasControl;

/**
 * @method static PositionFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $user_id
 * @property string $asset_symbol
 * @property string $asset_name
 * @property AssetType $asset_type
 * @property string $quantity
 * @property string $average_buy_price
 * @property string|null $current_price
 * @property string $currency
 */
#[UseFactory(PositionFactory::class)]
class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use HasControl, HasFactory, HasUlids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'asset_symbol',
        'asset_name',
        'asset_type',
        'quantity',
        'average_buy_price',
        'current_price',
        'currency',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'asset_type' => AssetType::class,
            'quantity' => 'decimal:8',
            'average_buy_price' => 'decimal:8',
            'current_price' => 'decimal:8',
        ];
    }

    /**
     * Get the user owning the position.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the transactions attached to the position.
     *
     * @return HasMany<InvestmentTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(InvestmentTransaction::class);
    }

    /**
     * Determine whether the position has a manually entered current price.
     */
    public function hasCurrentPrice(): bool
    {
        return $this->current_price !== null;
    }
}
