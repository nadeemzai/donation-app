<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SequenceKey;
use App\Models\Concerns\Auditable;
use App\Services\NumberSequenceService;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Goods received from a donor (FR-INV-01), with an estimated value. Recorded by InventoryService,
 * which puts every line into stock. Never edited — a mistake is fixed with a count correction.
 * Goods are not money: nothing is posted to the fund ledger.
 *
 * @property int $id
 * @property string $receipt_no
 * @property int|null $donor_id
 * @property string|null $donor_name
 * @property Carbon $received_at
 * @property string $total_value
 * @property string|null $notes
 * @property int|null $received_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Donor|null $donor
 * @property-read User|null $receiver
 */
class InKindDonation extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (InKindDonation $donation): void {
            if (blank($donation->getAttribute('receipt_no'))) {
                $donation->receipt_no = app(NumberSequenceService::class)->next(SequenceKey::InKindDonation);
            }
        });

        static::updating(function (InKindDonation $donation): void {
            if ($donation->isDirty(['receipt_no', 'donor_id', 'received_at'])) {
                throw new DomainException('Goods received cannot be changed. Make a count correction instead.');
            }
        });

        static::deleting(fn () => throw new DomainException('Goods received are never deleted.'));
    }

    public function giverName(): string
    {
        return $this->donor->name ?? $this->donor_name ?? 'Anonymous';
    }

    /**
     * @return HasMany<InKindDonationLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InKindDonationLine::class);
    }

    /**
     * @return BelongsTo<Donor, $this>
     */
    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'total_value' => 'decimal:2',
        ];
    }
}
