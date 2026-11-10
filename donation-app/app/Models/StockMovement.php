<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockDirection;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One stock change (FR-INV-02). Written only by InventoryService; never edited or deleted.
 * quantity is signed (+ in, − out); quantity_after is the item's stock right after it.
 *
 * @property int $id
 * @property int $item_id
 * @property StockDirection $direction
 * @property string $quantity
 * @property string $unit_value
 * @property string $quantity_after
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $note
 * @property int|null $moved_by
 * @property Carbon $moved_at
 * @property Carbon|null $created_at
 * @property-read InventoryItem $item
 * @property-read Model|null $source
 * @property-read User|null $mover
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new DomainException('A stock movement cannot be changed.'));
        static::deleting(fn () => throw new DomainException('A stock movement cannot be deleted.'));
    }

    public function value(): string
    {
        return bcmul((string) $this->quantity, (string) $this->unit_value, 2);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function mover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => StockDirection::class,
            'quantity' => 'decimal:2',
            'unit_value' => 'decimal:2',
            'quantity_after' => 'decimal:2',
            'moved_at' => 'datetime',
        ];
    }
}
