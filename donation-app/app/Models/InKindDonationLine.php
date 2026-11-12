<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $in_kind_donation_id
 * @property int $item_id
 * @property string $quantity
 * @property string $unit_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryItem $item
 */
class InKindDonationLine extends Model
{
    protected $guarded = ['id'];

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_value' => 'decimal:2'];
    }
}
