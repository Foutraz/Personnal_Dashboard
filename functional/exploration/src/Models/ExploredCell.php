<?php

namespace Functional\Exploration\Models;

use Functional\Exploration\Database\Factories\ExploredCellFactory;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lomkit\Access\Controls\HasControl;

/**
 * @method static ExploredCellFactory factory($count = null, $state = [])
 *
 * @property string $id
 * @property string $user_id
 * @property string $cell_key
 * @property float $lat
 * @property float $lng
 * @property int $visit_count
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 */
#[UseFactory(ExploredCellFactory::class)]
class ExploredCell extends Model
{
    /** @use HasFactory<ExploredCellFactory> */
    use HasControl, HasFactory, HasUlids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'cell_key',
        'lat',
        'lng',
        'visit_count',
        'first_seen_at',
        'last_seen_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'visit_count' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Get the user owning the explored cell.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
