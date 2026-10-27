<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FundType;
use App\Models\Concerns\Auditable;
use Database\Factories\FundFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A money bucket (FR-CMP-01). Every donation is credited to exactly one fund (BR-01).
 *
 * - is_zakat is always derived from the type; it can't be set by hand.
 * - Restricted funds must record what the money may be used for (BR-10).
 * - balance is not mass-assignable; only FundLedgerService (Week 3) changes it.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property FundType $type
 * @property bool $is_zakat
 * @property string|null $restriction
 * @property string|null $description
 * @property string $balance
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['code', 'name', 'type', 'restriction', 'description', 'is_active', 'sort_order'])]
class Fund extends Model
{
    /** @use HasFactory<FundFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Fund $fund): void {
            $fund->code = strtoupper(trim($fund->code));
            $fund->is_zakat = $fund->type->isZakat();

            if ($fund->type->requiresRestriction() && blank($fund->restriction)) {
                throw new DomainException('A restricted fund must say what the money may be used for.');
            }
        });
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * @param  Builder<Fund>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FundType::class,
            'is_zakat' => 'boolean',
            'is_active' => 'boolean',
            'balance' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }
}
