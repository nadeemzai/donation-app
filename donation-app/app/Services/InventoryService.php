<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockDirection;
use App\Models\Donor;
use App\Models\InKindDonation;
use App\Models\InKindDonationLine;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stock (Week 11 Day 3, FR-INV-01/02). The only place an item's quantity changes.
 *
 *   receive()  → goods from a donor: one GR-… record, every line into stock
 *   stockOut() → goods handed over (an in-kind disbursement when it is paid)
 *   adjust()   → a count correction after a physical count, with a reason
 *
 * Each change is one append-only movement line with the quantity after it; the item row is
 * locked while it is written, and stock can never go below zero.
 */
class InventoryService
{
    /**
     * @param  list<array{item_id: int|string, quantity: int|float|string, unit_value?: int|float|string|null}>  $lines
     */
    public function receive(array $lines, ?Donor $donor = null, ?string $donorName = null, ?Carbon $receivedAt = null, ?string $notes = null, ?User $by = null): InKindDonation
    {
        if ($lines === []) {
            throw new DomainException('Add at least one item.');
        }

        $receivedAt ??= now();

        if ($receivedAt->isFuture()) {
            throw new DomainException('The date received cannot be in the future.');
        }

        return DB::transaction(function () use ($lines, $donor, $donorName, $receivedAt, $notes, $by): InKindDonation {
            $donation = InKindDonation::query()->create([
                'donor_id' => $donor?->id,
                'donor_name' => $donor === null && filled($donorName) ? trim((string) $donorName) : null,
                'received_at' => $receivedAt,
                'notes' => filled($notes) ? trim((string) $notes) : null,
                'received_by' => $by?->id,
            ]);

            $total = '0.00';

            foreach ($lines as $line) {
                $item = InventoryItem::query()->find($line['item_id']) ?? throw new DomainException('Choose an item for every line.');

                if (! $item->is_active) {
                    throw new DomainException("{$item->name} is switched off. Switch it on first.");
                }

                $quantity = $this->quantity($line['quantity']);
                $unitValue = filled($line['unit_value'] ?? null) ? $this->money($line['unit_value']) : (string) $item->unit_value;

                InKindDonationLine::query()->create([
                    'in_kind_donation_id' => $donation->id,
                    'item_id' => $item->id,
                    'quantity' => $quantity,
                    'unit_value' => $unitValue,
                ]);

                $this->move($item, StockDirection::In, $quantity, $unitValue, $donation, 'Received '.$donation->receipt_no, $by);
                $total = bcadd($total, bcmul($quantity, $unitValue, 2), 2);
            }

            $donation->forceFill(['total_value' => $total])->save();

            activity('audit')->performedOn($donation)->causedBy($by)->event('goods_received')
                ->withProperties(['lines' => count($lines), 'value' => $total])
                ->log("Goods received {$donation->receipt_no} from {$donation->giverName()}, worth ".Money::rupees($total));

            return $donation->load('lines.item');
        });
    }

    /** Take goods out of stock (e.g. an in-kind disbursement being handed over). */
    public function stockOut(InventoryItem $item, string $quantity, ?Model $source, string $note, ?User $by = null): StockMovement
    {
        return $this->move($item, StockDirection::Out, $this->quantity($quantity), null, $source, $note, $by);
    }

    /** After a physical count: set the stock to what was counted, with the reason. */
    public function adjust(InventoryItem $item, string $counted, string $reason, ?User $by = null): StockMovement
    {
        $reason = trim($reason) !== '' ? trim($reason) : throw new DomainException('Write why the count is different.');
        $counted = $this->quantity($counted, allowZero: true);

        return DB::transaction(function () use ($item, $counted, $reason, $by): StockMovement {
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $difference = bcsub($counted, (string) $locked->quantity_on_hand, 2);

            if (bccomp($difference, '0', 2) === 0) {
                throw new DomainException('The count matches the stock — nothing to correct.');
            }

            if (bccomp($counted, $locked->reserved(), 2) < 0) {
                throw new DomainException('The count is below what approved disbursements still need ('.$locked->quantityLabel($locked->reserved()).'). Withdraw one of them first.');
            }

            return $this->write($locked, StockDirection::Adjust, $difference, (string) $locked->unit_value, null, 'Count: '.$reason, $by);
        });
    }

    private function move(InventoryItem $item, StockDirection $direction, string $quantity, ?string $unitValue, ?Model $source, string $note, ?User $by): StockMovement
    {
        return DB::transaction(function () use ($item, $direction, $quantity, $unitValue, $source, $note, $by): StockMovement {
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $signed = $direction === StockDirection::Out ? bcmul($quantity, '-1', 2) : $quantity;

            return $this->write($locked, $direction, $signed, $unitValue ?? (string) $locked->unit_value, $source, $note, $by);
        });
    }

    /** Caller holds the item lock. */
    private function write(InventoryItem $locked, StockDirection $direction, string $signed, string $unitValue, ?Model $source, string $note, ?User $by): StockMovement
    {
        $after = bcadd((string) $locked->quantity_on_hand, $signed, 2);

        if (bccomp($after, '0', 2) < 0) {
            throw new DomainException(sprintf(
                'Not enough %s in stock: %s on hand, %s needed.',
                $locked->name,
                $locked->quantityLabel((string) $locked->quantity_on_hand),
                $locked->quantityLabel(ltrim($signed, '-')),
            ));
        }

        // Query update: quantity_on_hand is not fillable; the movement line records the change.
        InventoryItem::query()->whereKey($locked->id)->update(['quantity_on_hand' => $after, 'updated_at' => now()]);

        return StockMovement::query()->create([
            'item_id' => $locked->id,
            'direction' => $direction,
            'quantity' => $signed,
            'unit_value' => $unitValue,
            'quantity_after' => $after,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'note' => mb_substr($note, 0, 255),
            'moved_by' => $by?->id,
            'moved_at' => now(),
        ]);
    }

    private function quantity(mixed $value, bool $allowZero = false): string
    {
        $raw = trim(is_scalar($value) ? (string) $value : '');

        if (preg_match('/^\d{1,9}(\.\d{1,2})?$/', $raw) !== 1 || (! $allowZero && bccomp($raw, '0', 2) <= 0)) {
            throw new DomainException('Enter a quantity more than zero, e.g. 25 or 2.5.');
        }

        return bcadd($raw, '0', 2);
    }

    private function money(mixed $value): string
    {
        try {
            return Money::normalise(trim(is_scalar($value) ? (string) $value : ''));
        } catch (InvalidArgumentException) {
            throw new DomainException('Enter the value per unit in rupees, e.g. 250.');
        }
    }
}
