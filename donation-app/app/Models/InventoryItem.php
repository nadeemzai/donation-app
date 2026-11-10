<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DisbursementStatus;
use App\Enums\InventoryCategory;
use App\Enums\InventoryUnit;
use App\Models\Concerns\Auditable;
use Database\Factories\InventoryItemFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A kind of goods the NGO keeps (FR-INV-01/02): rice (kg), blankets (piece), school bags (piece)…
 * quantity_on_hand is not fillable — it changes only through InventoryService, with a stock
 * movement line for every change.
 *
 * @property int $id
 * @property string $name
 * @property InventoryCategory $category
 * @property InventoryUnit $unit
 * @property string $unit_value
 * @property string $quantity_on_hand
 * @property string|null $reorder_level
 * @property bool $is_active
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'category', 'unit', 'unit_value', 'reorder_level', 'is_active', 'notes'])]
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use Auditable, HasFactory;

    protected $attributes = [
        'quantity_on_hand' => '0.00',
        'unit_value' => '0.00',
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (InventoryItem $item): void {
            $item->name = trim(preg_replace('/\s+/u', ' ', (string) $item->name) ?? '');

            if ($item->name === '') {
                throw new DomainException('Give the item a name.');
            }

            if (bccomp((string) $item->unit_value, '0', 2) < 0) {
                throw new DomainException('The value per unit cannot be negative.');
            }
        });

        static::deleting(function (): void {
            throw new DomainException('Items are never deleted — switch them off instead.');
        });
    }

    /**
     * Promised to in-kind disbursements that are approved but not yet handed over.
     */
    public function reserved(): string
    {
        if (! $this->exists) {
            return '0.00';
        }

        $sum = (string) DisbursementItem::query()
            ->where('item_id', $this->id)
            ->whereHas('disbursement', fn ($q) => $q->where('status', DisbursementStatus::Approved->value))
            ->sum('quantity');

        return bcadd($sum, '0', 2);
    }

    /** On hand minus what approved in-kind disbursements will take. */
    public function available(): string
    {
        return bcsub((string) $this->quantity_on_hand, $this->reserved(), 2);
    }

    public function stockValue(): string
    {
        return bcmul((string) $this->quantity_on_hand, (string) $this->unit_value, 2);
    }

    public function isLow(): bool
    {
        return $this->reorder_level !== null && bccomp((string) $this->quantity_on_hand, (string) $this->reorder_level, 2) <= 0;
    }

    /** "25 kg" */
    public function quantityLabel(string $quantity): string
    {
        $trimmed = rtrim(rtrim(bcadd($quantity, '0', 2), '0'), '.');

        return $trimmed.' '.mb_strtolower($this->unit->label());
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'item_id')->latest('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => InventoryCategory::class,
            'unit' => InventoryUnit::class,
            'unit_value' => 'decimal:2',
            'quantity_on_hand' => 'decimal:2',
            'reorder_level' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
